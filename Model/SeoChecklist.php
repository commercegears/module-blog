<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

/**
 * Per-post SEO checklist: title and meta description length, focus keyword placement, length, headings,
 * internal links and image alt texts.
 *
 * The admin editor runs the same rules live in view/adminhtml/web/js/form/seo-checklist.js; keep both in step.
 * The score (0-100) is stored on save and shown in the posts grid.
 */
class SeoChecklist
{
    public const GOOD = 'good';
    public const WARNING = 'warning';
    public const PROBLEM = 'problem';
    public const INFO = 'info';

    public const TITLE_MIN = 30;
    public const TITLE_MAX = 60;
    public const DESCRIPTION_MIN = 120;
    public const DESCRIPTION_MAX = 160;
    public const MIN_WORDS = 300;

    public function __construct(private readonly TextAnalyser $textAnalyser)
    {
    }

    /**
     * @param array<string, mixed> $data title, meta_title, meta_description, url_key, focus_keyword, content,
     *     featured_image, featured_image_alt
     * @param string[] $ownHosts host names of the store (links to them count as internal links)
     * @return array{score: int, checks: array<int, array{id: string, status: string, message: string}>}
     */
    public function analyse(array $data, array $ownHosts = [], int $wordsPerMinute = 200): array
    {
        $title = trim((string)($data['meta_title'] ?? '')) ?: trim((string)($data['title'] ?? ''));
        $description = trim((string)($data['meta_description'] ?? ''));
        $keyword = trim((string)($data['focus_keyword'] ?? ''));
        $html = (string)($data['content'] ?? '');
        $text = $this->textAnalyser->toPlainText($html);
        $words = $this->textAnalyser->wordCount($text);
        $checks = [];

        $length = mb_strlen($title);
        $checks[] = $this->check(
            'title',
            $length === 0 ? self::PROBLEM : ($length >= self::TITLE_MIN && $length <= self::TITLE_MAX ? self::GOOD
                : ($length > self::TITLE_MAX + 10 ? self::PROBLEM : self::WARNING)),
            (string)__('SEO title: %1 characters (aim for %2 to %3).', $length, self::TITLE_MIN, self::TITLE_MAX)
        );

        $length = mb_strlen($description);
        $checks[] = $this->check(
            'description',
            $length === 0 ? self::WARNING : ($length >= self::DESCRIPTION_MIN && $length <= self::DESCRIPTION_MAX
                ? self::GOOD : ($length > self::DESCRIPTION_MAX + 40 ? self::PROBLEM : self::WARNING)),
            $length === 0
                ? (string)__('Meta description: empty, so the start of the post is used. Write one of %1 to %2 characters.', self::DESCRIPTION_MIN, self::DESCRIPTION_MAX)
                : (string)__('Meta description: %1 characters (aim for %2 to %3).', $length, self::DESCRIPTION_MIN, self::DESCRIPTION_MAX)
        );

        if ($keyword === '') {
            $checks[] = $this->check('keyword', self::WARNING, (string)__('Focus keyword: not set. Add the phrase people search for to check its placement.'));
        } else {
            $needle = mb_strtolower($keyword);
            $checks[] = $this->check(
                'keyword_title',
                str_contains(mb_strtolower($title), $needle) ? self::GOOD : self::PROBLEM,
                (string)__('Focus keyword in the SEO title.')
            );
            $checks[] = $this->check(
                'keyword_description',
                str_contains(mb_strtolower($description), $needle) ? self::GOOD : self::WARNING,
                (string)__('Focus keyword in the meta description.')
            );
            $slug = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($this->ascii($keyword))), '-');
            $urlKey = strtolower((string)($data['url_key'] ?? ''));
            $checks[] = $this->check(
                'keyword_url',
                $slug !== '' && str_contains($urlKey, $slug) ? self::GOOD : self::WARNING,
                (string)__('Focus keyword in the URL key.')
            );
            $intro = implode(' ', array_slice(preg_split('/\s+/u', $text) ?: [], 0, 100));
            $checks[] = $this->check(
                'keyword_intro',
                str_contains(mb_strtolower($intro), $needle) ? self::GOOD : self::WARNING,
                (string)__('Focus keyword in the first 100 words.')
            );
        }

        $checks[] = $this->check(
            'length',
            $words >= self::MIN_WORDS ? self::GOOD : ($words >= self::MIN_WORDS / 2 ? self::WARNING : self::PROBLEM),
            (string)__('Length: %1 words (aim for at least %2).', $words, self::MIN_WORDS)
        );

        if (preg_match('/<h1[\s>]/i', $html)) {
            $checks[] = $this->check('headings', self::PROBLEM, (string)__('Headings: the content has an H1. The post title is the H1, so use H2 and H3 in the content.'));
        } else {
            $checks[] = $this->check(
                'headings',
                preg_match('/<h[23][\s>]/i', $html) ? self::GOOD : self::WARNING,
                (string)__('Subheadings (H2, H3) break the text into sections.')
            );
        }

        $internal = $this->countInternalLinks($html, $ownHosts);
        $checks[] = $this->check(
            'links',
            $internal > 0 ? self::GOOD : self::WARNING,
            (string)__('Internal links: %1 (link to at least one product, category or post).', $internal)
        );

        [$images, $missingAlt] = $this->countImages($html);
        $featured = trim((string)($data['featured_image'] ?? '')) !== '';
        $featuredAlt = trim((string)($data['featured_image_alt'] ?? '')) !== '';
        if ($images === 0 && !$featured) {
            $checks[] = $this->check('images', self::WARNING, (string)__('Images: none. A featured image is used in lists and when the post is shared.'));
        } elseif ($missingAlt > 0 || ($featured && !$featuredAlt)) {
            $checks[] = $this->check(
                'images',
                self::PROBLEM,
                (string)__('Image alt text: missing on %1 image(s).', $missingAlt + ($featured && !$featuredAlt ? 1 : 0))
            );
        } else {
            $checks[] = $this->check('images', self::GOOD, (string)__('Image alt text: every image has one.'));
        }

        $minutes = $this->textAnalyser->readingMinutes($words, $wordsPerMinute);
        $checks[] = $this->check('reading_time', self::INFO, (string)__('Reading time: about %1 min.', $minutes));

        return ['score' => $this->score($checks), 'checks' => $checks];
    }

    /**
     * @param array<int, array{status: string}> $checks
     */
    public function score(array $checks): int
    {
        $total = 0;
        $points = 0.0;
        foreach ($checks as $check) {
            if ($check['status'] === self::INFO) {
                continue;
            }
            $total++;
            $points += $check['status'] === self::GOOD ? 1.0 : ($check['status'] === self::WARNING ? 0.5 : 0.0);
        }
        return $total ? (int)round($points / $total * 100) : 0;
    }

    /**
     * @param string[] $ownHosts
     */
    public function countInternalLinks(string $html, array $ownHosts): int
    {
        if (!preg_match_all('/<a\s[^>]*href\s*=\s*(["\'])(.*?)\1/is', $html, $matches)) {
            return 0;
        }
        $hosts = array_map('strtolower', $ownHosts);
        $count = 0;
        foreach ($matches[2] as $href) {
            $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($href === '' || $href[0] === '#' || preg_match('/^(mailto|tel|javascript):/i', $href)) {
                continue;
            }
            if (str_starts_with($href, '{{') || (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) && !str_starts_with($href, '//'))) {
                $count++;
                continue;
            }
            $host = strtolower((string)parse_url($href, PHP_URL_HOST));
            if ($host !== '' && in_array(preg_replace('/^www\./', '', $host), array_map(
                static fn ($h) => preg_replace('/^www\./', '', $h),
                $hosts
            ), true)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @return array{0: int, 1: int} images, images without alt text
     */
    public function countImages(string $html): array
    {
        if (!preg_match_all('/<img\b[^>]*>/i', $html, $matches)) {
            return [0, 0];
        }
        $missing = 0;
        foreach ($matches[0] as $tag) {
            if (!preg_match('/\salt\s*=\s*(["\'])\s*\S.*?\1/is', $tag)) {
                $missing++;
            }
        }
        return [count($matches[0]), $missing];
    }

    /**
     * @return array{id: string, status: string, message: string}
     */
    private function check(string $id, string $status, string $message): array
    {
        return ['id' => $id, 'status' => $status, 'message' => $message];
    }

    private function ascii(string $value): string
    {
        $converted = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : false;
        return $converted !== false ? $converted : $value;
    }
}
