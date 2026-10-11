<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Storefront addresses of blog pages. Paths are relative to the store view's base URL.
 */
class Url
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getHomePath(?int $storeId = null): string
    {
        $route = $this->config->getRoute($storeId);
        return $this->config->getUrlSuffix($storeId) === '/' ? $route . '/' : $route;
    }

    public function getPostPath(string $urlKey, ?int $storeId = null): string
    {
        return $this->config->getRoute($storeId) . '/' . $urlKey . $this->config->getUrlSuffix($storeId);
    }

    /**
     * @param string $type category, tag or author
     */
    public function getEntityPath(string $type, string $urlKey, ?int $storeId = null): string
    {
        return $this->config->getRoute($storeId) . '/' . $this->config->getPrefix($type, $storeId) . '/' . $urlKey
            . $this->config->getUrlSuffix($storeId);
    }

    public function getRssPath(?int $storeId = null): string
    {
        return $this->config->getRoute($storeId) . '/rss.xml';
    }

    public function toAbsolute(string $path, ?int $storeId = null): string
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore($storeId);
        return $store->getBaseUrl(UrlInterface::URL_TYPE_LINK) . ltrim($path, '/');
    }

    public function getHomeUrl(?int $storeId = null): string
    {
        return $this->toAbsolute($this->getHomePath($storeId), $storeId);
    }

    public function getPostUrl(Post $post, ?int $storeId = null): string
    {
        return $this->toAbsolute($this->getPostPath($post->getUrlKey(), $storeId), $storeId);
    }

    public function getCategoryUrl(Category $category, ?int $storeId = null): string
    {
        return $this->toAbsolute($this->getEntityPath('category', $category->getUrlKey(), $storeId), $storeId);
    }

    public function getTagUrl(Tag $tag, ?int $storeId = null): string
    {
        return $this->toAbsolute($this->getEntityPath('tag', $tag->getUrlKey(), $storeId), $storeId);
    }

    public function getAuthorUrl(Author $author, ?int $storeId = null): string
    {
        return $this->toAbsolute($this->getEntityPath('author', $author->getUrlKey(), $storeId), $storeId);
    }

    public function getRssUrl(?int $storeId = null, string $categoryKey = ''): string
    {
        $url = $this->toAbsolute($this->getRssPath($storeId), $storeId);
        return $categoryKey !== '' ? $url . '?category=' . rawurlencode($categoryKey) : $url;
    }

    public function getMediaUrl(string $file, ?int $storeId = null): string
    {
        if ($file === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $file)) {
            return $file;
        }
        /** @var Store $store */
        $store = $this->storeManager->getStore($storeId);
        $media = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        // "/path" is relative to pub/media (images picked from the media gallery), else to pub/media/commercegears_blog
        return str_starts_with($file, '/') ? $media . ltrim($file, '/') : $media . ImageUploader::BASE_PATH . '/' . $file;
    }
}
