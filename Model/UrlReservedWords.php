<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

/**
 * Post URL keys that would clash with the blog's own addresses (/blog/category, /blog/rss.xml ...).
 */
class UrlReservedWords
{
    public function __construct(private readonly Config $config)
    {
    }

    public function isReserved(string $urlKey, int $storeId): bool
    {
        $reserved = ['rss', 'rss.xml', 'page', 'search', 'feed'];
        foreach (['category', 'tag', 'author'] as $type) {
            $reserved[] = $this->config->getPrefix($type, $storeId ?: null);
        }
        return in_array(strtolower($urlKey), $reserved, true);
    }
}
