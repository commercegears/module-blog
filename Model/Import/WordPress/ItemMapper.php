<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Import\WordPress;

/**
 * Maps WordPress export data to blog data (no framework dependencies, unit tested).
 */
class ItemMapper
{
    public const STATUS_DRAFT = 0;
    public const STATUS_PUBLISHED = 1;

    public const COMMENT_PENDING = 0;
    public const COMMENT_APPROVED = 1;

    /** WordPress' default category; posts that only have it get no category. */
    public const DEFAULT_CATEGORY = 'uncategorized';

    /**
     * Short, stable key of the WordPress site, part of every external ID so two sites never collide.
     */
    public function siteKey(string $baseUrl): string
    {
        $parts = parse_url(strtolower(trim($baseUrl)));
        $host = preg_replace('/^www\./', '', (string)($parts['host'] ?? $baseUrl));
        return substr(sha1($host . rtrim((string)($parts['path'] ?? ''), '/')), 0, 8);
    }

    public function externalId(string $siteKey, string $type, string|int $id): string
    {
        return mb_substr('wp:' . $siteKey . ':' . $type . ':' . $id, 0, 128);
    }

    public function isPost(array $item): bool
    {
        return ($item['post_type'] ?? '') === 'post' && ($item['status'] ?? '') !== 'auto-draft'
            && ($item['status'] ?? '') !== 'trash' && ($item['status'] ?? '') !== 'inherit';
    }

    /**
     * Status and publish date (UTC) of a post.
     *
     * publish => published; future => published with the future date (scheduled); everything else (draft,
     * pending, private, password protected) => draft. With $asDrafts every post becomes a draft.
     *
     * @return array{status: int, publish_at: ?string}
     */
    public function mapStatus(array $item, bool $asDrafts = false): array
    {
        $date = $this->utcDate((string)($item['post_date_gmt'] ?? ''), (string)($item['post_date'] ?? ''));
        $status = (string)($item['status'] ?? '');
        $published = in_array($status, ['publish', 'future'], true) && trim((string)($item['post_password'] ?? '')) === '';
        if ($asDrafts || !$published) {
            return ['status' => self::STATUS_DRAFT, 'publish_at' => $status === 'publish' || $status === 'future' ? $date : null];
        }
        return ['status' => self::STATUS_PUBLISHED, 'publish_at' => $date];
    }

    /**
     * WordPress writes "0000-00-00 00:00:00" for unpublished posts. Local dates are used as UTC when there is
     * no GMT date (the export does not say which time zone the site used).
     */
    public function utcDate(string $gmt, string $local = ''): ?string
    {
        foreach ([$gmt, $local] as $value) {
            $value = trim($value);
            if ($value === '' || str_starts_with($value, '0000')) {
                continue;
            }
            $time = strtotime($value . ' UTC');
            if ($time !== false && $time > 0) {
                return gmdate('Y-m-d H:i:s', $time);
            }
        }
        return null;
    }

    /**
     * URL key from the WordPress slug (or the title).
     */
    public function slug(array $item): string
    {
        $slug = rawurldecode((string)($item['post_name'] ?? ''));
        return trim($slug) !== '' ? $slug : (string)($item['title'] ?? '');
    }

    /**
     * Meta title, meta description, focus keyword and robots from Yoast SEO, Rank Math or All in One SEO.
     * Titles with template variables (%%title%%, %title%) are not taken over.
     *
     * @param array<string, string> $meta
     * @return array{meta_title: string, meta_description: string, focus_keyword: string, meta_robots: string}
     */
    public function mapSeo(array $meta): array
    {
        $pick = static function (array $keys) use ($meta): string {
            foreach ($keys as $key) {
                $value = trim(html_entity_decode((string)($meta[$key] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($value !== '' && !preg_match('/%%?[a-z_]+%%?|#[a-z_]+/i', $value)) {
                    return $value;
                }
            }
            return '';
        };
        $keyword = $pick(['_yoast_wpseo_focuskw', 'rank_math_focus_keyword', '_aioseo_keyphrases_focus']);
        $keyword = trim(explode(',', $keyword)[0]);
        $robots = '';
        if (($meta['_yoast_wpseo_meta-robots-noindex'] ?? '') === '1') {
            $robots = ($meta['_yoast_wpseo_meta-robots-nofollow'] ?? '') === '1' ? 'NOINDEX,NOFOLLOW' : 'NOINDEX,FOLLOW';
        }
        return [
            'meta_title' => mb_substr($pick(['_yoast_wpseo_title', 'rank_math_title', '_aioseo_title']), 0, 255),
            'meta_description' => $pick(['_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description']),
            'focus_keyword' => mb_substr($keyword, 0, 255),
            'meta_robots' => $robots,
        ];
    }

    /**
     * @return array<int, array{slug: string, name: string}>
     */
    public function terms(array $item, string $domain): array
    {
        $result = [];
        foreach ((array)($item['terms'] ?? []) as $term) {
            if (($term['domain'] ?? '') !== $domain) {
                continue;
            }
            $slug = rawurldecode((string)($term['slug'] ?? ''));
            $name = html_entity_decode((string)($term['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($slug === '' && $name === '') {
                continue;
            }
            if ($domain === 'category' && $slug === self::DEFAULT_CATEGORY) {
                continue;
            }
            $result[$slug !== '' ? $slug : $name] = ['slug' => $slug, 'name' => $name !== '' ? $name : $slug];
        }
        return array_values($result);
    }

    /**
     * Comment data, NULL for comments that are not imported (spam, trash, pingbacks and trackbacks).
     *
     * @return array{status: int, author_name: string, author_email: ?string, content: string, created_at: string}|null
     */
    public function mapComment(array $comment): ?array
    {
        $approved = (string)($comment['approved'] ?? '');
        $type = (string)($comment['type'] ?? '');
        if (!in_array($approved, ['0', '1'], true) || in_array($type, ['pingback', 'trackback'], true)) {
            return null;
        }
        $content = trim(str_replace(["\r\n", "\r"], "\n", (string)($comment['content'] ?? '')));
        if ($content === '') {
            return null;
        }
        $content = trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $email = trim((string)($comment['author_email'] ?? ''));
        return [
            'status' => $approved === '1' ? self::COMMENT_APPROVED : self::COMMENT_PENDING,
            'author_name' => mb_substr(trim(strip_tags((string)($comment['author'] ?? ''))) ?: 'Anonymous', 0, 100),
            'author_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'content' => $content,
            'created_at' => $this->utcDate((string)($comment['date_gmt'] ?? ''), (string)($comment['date'] ?? ''))
                ?? gmdate('Y-m-d H:i:s'),
        ];
    }

    /**
     * Path of an old WordPress address for a redirect ("2024/05/my-post/"); NULL for addresses that cannot be
     * redirected (home page, query-string permalinks like "?p=12").
     */
    public function oldPath(string $link): ?string
    {
        $parts = parse_url(trim($link));
        if ($parts === false || !empty($parts['query'])) {
            return null;
        }
        $path = ltrim((string)($parts['path'] ?? ''), '/');
        if ($path === '' || strlen($path) > 255 || str_contains($path, '..')) {
            return null;
        }
        return $path;
    }

    /**
     * Host names of the WordPress site (images on these hosts are downloaded).
     *
     * @param array<string, string> $site
     * @return string[]
     */
    public function siteHosts(array $site): array
    {
        $hosts = [];
        foreach (['base_site_url', 'base_blog_url', 'link'] as $key) {
            $host = strtolower((string)parse_url((string)($site[$key] ?? ''), PHP_URL_HOST));
            if ($host !== '') {
                $bare = (string)preg_replace('/^www\./', '', $host);
                $hosts[$bare] = $bare;
            }
        }
        return array_values($hosts);
    }

    /**
     * True when the URL is on the WordPress site (or one of its subdomains) or on WordPress.com's image CDN.
     *
     * @param string[] $siteHosts
     */
    public function isSiteImage(string $url, array $siteHosts): bool
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }
        if (preg_match('/^i[0-3]\.wp\.com$/', $host)) {
            return true;
        }
        foreach ($siteHosts as $siteHost) {
            if ($host === $siteHost || str_ends_with($host, '.' . $siteHost)) {
                return true;
            }
        }
        return false;
    }

    /**
     * File name for a downloaded image (without the WordPress size suffix "-1024x768").
     */
    public function imageFileName(string $url): string
    {
        $name = rawurldecode(basename((string)parse_url($url, PHP_URL_PATH)));
        $name = (string)preg_replace('/-\d{2,5}x\d{2,5}(\.[a-z0-9]+)$/i', '$1', $name);
        return $name !== '' ? $name : 'image';
    }
}
