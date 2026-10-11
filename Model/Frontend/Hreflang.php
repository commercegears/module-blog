<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Frontend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\CurrentPage;
use Softaware\Blog\Model\ResourceModel\Category as CategoryResource;
use Softaware\Blog\Model\ResourceModel\Post as PostResource;
use Softaware\Blog\Model\Url;

/**
 * Alternate links between store views for the blog home page, categories and posts. One store view per
 * language: the current store view, then store views of the same website, then the lowest store ID.
 */
class Hreflang
{
    public function __construct(
        private readonly CurrentPage $currentPage,
        private readonly Config $config,
        private readonly Url $url,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PostResource $postResource,
        private readonly CategoryResource $categoryResource
    ) {
    }

    /**
     * @return array<string, string> hreflang => URL (empty when fewer than two languages)
     */
    public function getLinks(): array
    {
        $current = $this->storeManager->getStore();
        $currentId = (int)$current->getId();
        if (!$this->config->getFlag('seo/hreflang', $currentId) || $this->currentPage->isPreview()
            || $this->currentPage->getPageNumber() > 1
        ) {
            return [];
        }
        $type = $this->currentPage->getType();
        if (!in_array($type, [CurrentPage::TYPE_HOME, CurrentPage::TYPE_CATEGORY, CurrentPage::TYPE_POST], true)) {
            return [];
        }
        $candidates = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int)$store->getId();
            if (!$store->getIsActive() || !$this->config->isEnabled($storeId)) {
                continue;
            }
            $url = $this->getUrlForStore($type, $storeId);
            if ($url === null) {
                continue;
            }
            $language = strtolower(str_replace('_', '-', (string)$this->scopeConfig->getValue(
                'general/locale/code',
                ScopeInterface::SCOPE_STORE,
                $storeId
            )));
            $rank = $storeId === $currentId ? 0 : ((int)$store->getWebsiteId() === (int)$current->getWebsiteId() ? 1 : 2);
            $candidates[] = ['language' => $language, 'url' => $url, 'rank' => $rank, 'id' => $storeId];
        }
        usort($candidates, static fn ($a, $b) => [$a['rank'], $a['id']] <=> [$b['rank'], $b['id']]);
        $links = [];
        foreach ($candidates as $candidate) {
            if ($candidate['language'] !== '' && !isset($links[$candidate['language']])) {
                $links[$candidate['language']] = $candidate['url'];
            }
        }
        if (count($links) < 2) {
            return [];
        }
        $defaultStoreId = (int)$this->storeManager->getDefaultStoreView()?->getId();
        foreach ($candidates as $candidate) {
            if ($candidate['id'] === $defaultStoreId && in_array($candidate['url'], $links, true)) {
                $links['x-default'] = $candidate['url'];
            }
        }
        return $links;
    }

    private function getUrlForStore(string $type, int $storeId): ?string
    {
        switch ($type) {
            case CurrentPage::TYPE_HOME:
                return $this->url->getHomeUrl($storeId);
            case CurrentPage::TYPE_CATEGORY:
                $category = $this->currentPage->getCategory();
                $content = $this->categoryResource->loadStoreContent((int)$category->getId(), $storeId);
                $key = $content['url_key'] ?? (string)$category->getDefaultValue('url_key');
                return $this->url->toAbsolute($this->url->getEntityPath('category', $key, $storeId), $storeId);
            case CurrentPage::TYPE_POST:
                $post = $this->currentPage->getPost();
                $storeIds = $post->getStoreIds();
                if (!in_array(0, $storeIds, true) && !in_array($storeId, $storeIds, true)) {
                    return null;
                }
                $content = $this->postResource->loadStoreContent((int)$post->getId(), $storeId);
                if ((string)$post->getData('canonical_url') !== '') {
                    return null;
                }
                $key = $content['url_key'] ?? (string)$post->getDefaultValue('url_key');
                return $this->url->toAbsolute($this->url->getPostPath($key, $storeId), $storeId);
        }
        return null;
    }
}
