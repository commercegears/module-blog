<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\CurrentPage;
use CommerceGears\Blog\Model\Frontend\Hreflang;
use CommerceGears\Blog\Model\Frontend\PageMeta;
use CommerceGears\Blog\Model\Frontend\StructuredData;

/**
 * <head> additions of blog pages: Open Graph / Twitter tags, hreflang links and JSON-LD.
 */
class Head extends Template
{
    protected $_template = 'CommerceGears_Blog::head.phtml';

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly CurrentPage $currentPage,
        private readonly PageMeta $pageMeta,
        private readonly StructuredData $structuredData,
        private readonly Hreflang $hreflang,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isBlogPage(): bool
    {
        return $this->currentPage->isBlogPage();
    }

    /**
     * @return array<int, array{0: string, 1: string}> property => content
     */
    public function getOpenGraphTags(): array
    {
        $storeId = (int)$this->_storeManager->getStore()->getId();
        if (!$this->config->getFlag('seo/open_graph', $storeId) || $this->currentPage->isPreview()) {
            return [];
        }
        $meta = $this->pageMeta->get();
        $isPost = $this->currentPage->getType() === CurrentPage::TYPE_POST;
        $tags = [
            ['og:type', $isPost ? 'article' : 'website'],
            ['og:site_name', (string)$this->_scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE, $storeId)
                ?: (string)$this->_storeManager->getStore()->getFrontendName()],
            ['og:locale', (string)$this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId)],
            ['og:title', $meta['title']],
            ['og:description', $meta['description']],
            ['og:url', $meta['canonical']],
        ];
        if ($meta['image'] !== '') {
            $tags[] = ['og:image', $meta['image']];
            $tags[] = ['og:image:alt', $meta['image_alt']];
        }
        if ($isPost) {
            $post = $this->currentPage->getPost();
            $tags[] = ['article:published_time', $this->iso($post->getPublishAt())];
            $tags[] = ['article:modified_time', $this->iso((string)$post->getData('updated_at'))];
        }
        return array_values(array_filter($tags, static fn ($tag) => trim((string)$tag[1]) !== ''));
    }

    /**
     * @return array<string, string>
     */
    public function getTwitterTags(): array
    {
        $storeId = (int)$this->_storeManager->getStore()->getId();
        if (!$this->config->getFlag('seo/open_graph', $storeId) || $this->currentPage->isPreview()) {
            return [];
        }
        $meta = $this->pageMeta->get();
        return array_filter([
            'twitter:card' => $meta['image'] !== '' ? 'summary_large_image' : 'summary',
            'twitter:title' => $meta['title'],
            'twitter:description' => $meta['description'],
            'twitter:image' => $meta['image'],
        ], static fn ($value) => $value !== '');
    }

    /**
     * @return array<string, string>
     */
    public function getHreflangLinks(): array
    {
        try {
            return $this->hreflang->getLinks();
        } catch (\Throwable $e) {
            $this->_logger->error('CommerceGears_Blog hreflang: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return string[] encoded JSON-LD documents
     */
    public function getJsonLd(): array
    {
        try {
            $nodes = $this->structuredData->getNodes();
        } catch (\Throwable $e) {
            $this->_logger->error('CommerceGears_Blog JSON-LD: ' . $e->getMessage());
            return [];
        }
        return array_map(
            static fn ($node) => (string)json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP),
            $nodes
        );
    }

    private function iso(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        try {
            return (new \DateTime($utc, new \DateTimeZone('UTC')))->format(DATE_ATOM);
        } catch (\Exception) {
            return '';
        }
    }
}
