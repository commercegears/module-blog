<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

use Magento\Framework\Filter\FilterManager;

/**
 * URL keys: transliterated, lower case, letters, digits and hyphens.
 */
class UrlKeyFormatter
{
    public function __construct(private readonly FilterManager $filterManager)
    {
    }

    public function format(string $value): string
    {
        $key = (string)$this->filterManager->translitUrl(trim($value));
        $key = strtolower(trim((string)preg_replace('/[^a-z0-9-]+/i', '-', $key), '-'));
        $key = (string)preg_replace('/-{2,}/', '-', $key);
        return mb_substr($key, 0, 200);
    }
}
