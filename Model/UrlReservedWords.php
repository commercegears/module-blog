<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model;

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
