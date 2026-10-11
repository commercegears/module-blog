<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model;

/**
 * Plain-text helpers shared by the storefront and the SEO checklist. No framework dependencies.
 */
class TextAnalyser
{
    public function toPlainText(string $html): string
    {
        $html = (string)preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = (string)preg_replace('#\{\{[^}]*\}\}#', ' ', $html);
        $html = (string)preg_replace('#<(br|/p|/div|/h[1-6]|/li|/tr|/blockquote)\b[^>]*>#i', "$0 ", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    public function wordCount(string $text): int
    {
        if (trim($text) === '') {
            return 0;
        }
        return count(preg_split('/\s+/u', trim($text)) ?: []);
    }

    public function readingMinutes(int $words, int $wordsPerMinute): int
    {
        return $words <= 0 ? 0 : max(1, (int)ceil($words / max(1, $wordsPerMinute)));
    }

    /**
     * Cuts at a word boundary and adds an ellipsis when the text is longer than $length characters.
     */
    public function truncate(string $text, int $length): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $length * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }
        return rtrim($cut, " ,.;:-") . '…';
    }
}
