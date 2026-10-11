<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Import\WordPress;

/**
 * Turns WordPress post content into plain HTML for the blog (no framework dependencies, unit tested):
 * block editor comments removed, classic-editor line breaks turned into paragraphs, [caption] into <figure>,
 * [embed] into a link, and image addresses replaced once the images were downloaded.
 */
class ContentConverter
{
    private const BLOCK_TAGS = 'address|article|aside|blockquote|dd|details|div|dl|dt|figcaption|figure|footer|form|'
        . 'h[1-6]|header|hr|iframe|li|nav|ol|p|pre|section|table|tbody|td|tfoot|th|thead|tr|ul|video|audio|script|style';

    public function convert(string $content): string
    {
        $isBlockEditor = str_contains($content, '<!-- wp:');
        $html = str_replace(["\r\n", "\r"], "\n", $content);
        $html = (string)preg_replace('/<!--\s*\/?wp:.*?-->/s', '', $html);
        $html = $this->convertCaptions($html);
        $html = (string)preg_replace_callback(
            '#\[embed[^\]]*\](https?://[^\[\s]+)\[/embed\]#i',
            static fn (array $m) => '<p><a href="' . htmlspecialchars($m[1], ENT_QUOTES) . '">'
                . htmlspecialchars($m[1], ENT_QUOTES) . '</a></p>',
            $html
        );
        if (!$isBlockEditor && !preg_match('/<p[\s>]/i', $html)) {
            $html = $this->autop($html);
        }
        $html = (string)preg_replace("/\n{3,}/", "\n\n", $html);
        return trim($html);
    }

    /**
     * Paragraphs for classic-editor content: a blank line starts a new paragraph, a single line break becomes <br>.
     */
    public function autop(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        // keep block elements on their own lines
        $text = (string)preg_replace('#(<(?:' . self::BLOCK_TAGS . ')[\s/>])#i', "\n\n$1", $text);
        $text = (string)preg_replace('#(</(?:' . self::BLOCK_TAGS . ')>)#i', "$1\n\n", $text);
        $chunks = preg_split("/\n\s*\n/", $text) ?: [];
        $out = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (preg_match('#^</?(?:' . self::BLOCK_TAGS . ')[\s/>]#i', $chunk)) {
                $out[] = $chunk;
                continue;
            }
            $out[] = '<p>' . str_replace("\n", "<br>\n", $chunk) . '</p>';
        }
        return implode("\n", $out);
    }

    /**
     * [caption id="" align="alignnone" width="300"]<img ...> Caption text[/caption] => <figure>
     */
    public function convertCaptions(string $html): string
    {
        return (string)preg_replace_callback(
            '#\[caption([^\]]*)\](.*?)\[/caption\]#is',
            static function (array $m): string {
                $inner = trim($m[2]);
                $caption = '';
                if (preg_match('#^((?:<a\b[^>]*>\s*)?<img\b[^>]*>(?:\s*</a>)?)(.*)$#is', $inner, $parts)) {
                    $media = $parts[1];
                    $caption = trim($parts[2]);
                } else {
                    $media = $inner;
                }
                if ($caption === '' && preg_match('/\scaption="([^"]*)"/', $m[1], $attr)) {
                    $caption = $attr[1];
                }
                return '<figure class="wp-caption">' . $media
                    . ($caption !== '' ? '<figcaption>' . $caption . '</figcaption>' : '') . '</figure>';
            },
            $html
        );
    }

    /**
     * Addresses of images in the content: <img src> and links that point straight at an image file.
     *
     * @return string[]
     */
    public function findImageUrls(string $html): array
    {
        $urls = [];
        if (preg_match_all('/<img\b[^>]*?\ssrc\s*=\s*(["\'])(.*?)\1/is', $html, $matches)) {
            foreach ($matches[2] as $url) {
                $urls[] = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        if (preg_match_all('/<a\b[^>]*?\shref\s*=\s*(["\'])(.*?)\1/is', $html, $matches)) {
            foreach ($matches[2] as $url) {
                $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match('/\.(jpe?g|png|gif|webp)(\?.*)?$/i', $url)) {
                    $urls[] = $url;
                }
            }
        }
        return array_values(array_unique(array_filter($urls, static fn ($url) => (bool)preg_match('#^https?://#i', $url))));
    }

    /**
     * Replaces image addresses (img src and links to the image) and drops srcset/sizes of replaced images.
     *
     * @param array<string, string> $map old URL => new src (for example {{media url="..."}})
     */
    public function replaceImageUrls(string $html, array $map): string
    {
        if (!$map) {
            return $html;
        }
        $html = (string)preg_replace_callback('/<img\b[^>]*>/is', function (array $m) use ($map): string {
            $tag = $m[0];
            if (!preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/is', $tag, $src)) {
                return $tag;
            }
            $old = html_entity_decode(trim($src[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (!isset($map[$old])) {
                return $tag;
            }
            $tag = str_replace($src[0], ' src="' . $this->attr($map[$old]) . '"', $tag);
            return (string)preg_replace('/\s(srcset|sizes)\s*=\s*(["\']).*?\2/is', '', $tag);
        }, $html);
        return (string)preg_replace_callback('/(<a\b[^>]*?\shref\s*=\s*)(["\'])(.*?)\2/is', function (array $m) use ($map): string {
            $old = html_entity_decode(trim($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return isset($map[$old]) ? $m[1] . '"' . $this->attr($map[$old]) . '"' : $m[0];
        }, $html);
    }

    /**
     * Shortcodes that are left in the content (for the import log).
     *
     * @return string[]
     */
    public function findShortcodes(string $html): array
    {
        if (!preg_match_all('/\[([a-z][a-z0-9_-]*)(?:\s[^\]]*)?\]/i', $html, $matches)) {
            return [];
        }
        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    private function attr(string $value): string
    {
        // {{media url="..."}} directives are kept as they are (as Magento's editor saves them); they are replaced
        // by the real address before the page is sent
        return str_starts_with($value, '{{') ? $value : htmlspecialchars($value, ENT_QUOTES);
    }
}
