<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Comment;

/**
 * Spam rules for comments that need no framework (unit tested).
 */
class Guard
{
    /** Hidden field; real visitors leave it empty. */
    public const HONEYPOT_FIELD = 'swb_website';

    public function isHoneypotFilled(?string $value): bool
    {
        return trim((string)$value) !== '';
    }

    /**
     * True when the text contains a web address. Such comments can be held for review (links are what spam is for).
     */
    public function containsLink(string $text): bool
    {
        return (bool)preg_match(
            '~(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|info|biz|ru|xyz|top|io|co|uk|shop|online|site|link|click)\b)~i',
            $text
        );
    }

    public function hashIp(string $ip, string $salt): ?string
    {
        return $ip === '' ? null : hash('sha256', $salt . '|' . $ip);
    }

    /**
     * @return string|null error message, NULL when valid
     */
    public function validateText(string $text, int $min, int $max): ?string
    {
        $length = mb_strlen(trim($text));
        if ($length < $min) {
            return (string)__('Please write at least %1 characters.', $min);
        }
        if ($length > $max) {
            return (string)__('Please keep your comment under %1 characters.', $max);
        }
        return null;
    }
}
