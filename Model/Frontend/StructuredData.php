<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Frontend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\CurrentPage;
use CommerceGears\Blog\Model\Post;
use CommerceGears\Blog\Model\TextAnalyser;
use CommerceGears\Blog\Model\Url;

/**
 * JSON-LD for blog pages: BlogPosting, Blog, CollectionPage, ProfilePage and BreadcrumbList.
 *
 * CommerceGears_Seo (when installed) does not describe blog pages, so there is no duplicate; its Organisation
 * node, when set to "every page", sits next to these nodes.
 */
class StructuredData
{
    public function __construct(
        private readonly CurrentPage $currentPage,
        private readonly Config $config,
        private readonly PageMeta $pageMeta,
        private readonly Url $url,
        private readonly Catalog $catalog,
        private readonly ContentRenderer $contentRenderer,
        private readonly TextAnalyser $textAnalyser,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getNodes(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->getFlag('seo/json_ld', $storeId) || !$this->currentPage->isBlogPage()
            || $this->currentPage->isPreview()
        ) {
            return [];
        }
        $meta = $this->pageMeta->get();
        $nodes = [];
        switch ($this->currentPage->getType()) {
            case CurrentPage::TYPE_POST:
                $nodes[] = $this->buildPosting($this->currentPage->getPost(), $storeId, $meta);
                break;
            case CurrentPage::TYPE_HOME:
                $nodes[] = array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'Blog',
                    'name' => $this->config->getTitle($storeId),
                    'url' => $meta['canonical'],
                    'description' => $meta['description'],
                    'inLanguage' => $this->getLanguage($storeId),
                    'publisher' => $this->getPublisher($storeId),
                ]);
                break;
            case CurrentPage::TYPE_AUTHOR:
                $author = $this->currentPage->getAuthor();
                $nodes[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'ProfilePage',
                    'url' => $meta['canonical'],
                    'mainEntity' => array_filter([
                        '@type' => 'Person',
                        'name' => $author->getName(),
                        'jobTitle' => (string)$author->getData('job_title'),
                        'description' => $meta['description'],
                        'image' => $meta['image'],
                        'sameAs' => array_values(array_filter([
                            (string)$author->getData('website_url'),
                            (string)$author->getData('linkedin_url'),
                            (string)$author->getData('x_url'),
                        ])),
                    ]),
                ];
                break;
            default:
                $nodes[] = array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => $meta['heading'],
                    'url' => $meta['canonical'],
                    'description' => $meta['description'],
                ]);
        }
        $breadcrumbs = $this->buildBreadcrumbs($meta['breadcrumbs'], $meta['canonical']);
        if ($breadcrumbs) {
            $nodes[] = $breadcrumbs;
        }
        return $nodes;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function buildPosting(Post $post, int $storeId, array $meta): array
    {
        $author = $post->getAuthorId() ? $this->catalog->getAuthor($post->getAuthorId()) : null;
        $tags = $this->catalog->getTags($post->getTagIds(), $storeId);
        $category = $this->pageMeta->getMainCategory($post->getCategoryIds(), $storeId);
        $text = $this->textAnalyser->toPlainText($this->contentRenderer->getContentHtml($post, $storeId));
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $this->textAnalyser->truncate($post->getTitle(), 110),
            'description' => $meta['description'],
            'url' => $meta['canonical'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $meta['canonical']],
            'datePublished' => $this->iso($post->getPublishAt()),
            'dateModified' => $this->iso((string)$post->getData('updated_at')) ?: $this->iso($post->getPublishAt()),
            'inLanguage' => $this->getLanguage($storeId),
            'wordCount' => $this->textAnalyser->wordCount($text),
            'publisher' => $this->getPublisher($storeId),
        ];
        if ($meta['image'] !== '') {
            $node['image'] = [$meta['image']];
        }
        if ($author) {
            $node['author'] = [[
                '@type' => 'Person',
                'name' => $author->getName(),
                'url' => $this->url->getAuthorUrl($author, $storeId),
            ]];
        } else {
            $node['author'] = [$this->getPublisher($storeId)];
        }
        if ($tags) {
            $node['keywords'] = implode(', ', array_map(static fn ($tag) => $tag->getName(), $tags));
        }
        if ($category) {
            $node['articleSection'] = $category->getName();
        }
        return array_filter($node, static fn ($value) => $value !== '' && $value !== null && $value !== []);
    }

    /**
     * @param array<int, array{label: string, url?: string}> $crumbs
     * @return array<string, mixed>|null
     */
    public function buildBreadcrumbs(array $crumbs, string $currentUrl): ?array
    {
        if (count($crumbs) < 2) {
            return null;
        }
        $items = [];
        foreach (array_values($crumbs) as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['label'],
                'item' => $crumb['url'] ?? $currentUrl,
            ];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPublisher(int $storeId): array
    {
        $name = $this->config->getValue('seo/publisher_name', $storeId)
            ?: (string)$this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE, $storeId)
            ?: (string)$this->storeManager->getStore($storeId)->getFrontendName();
        $publisher = ['@type' => 'Organization', 'name' => $name, 'url' => $this->storeManager->getStore($storeId)->getBaseUrl()];
        $logo = (string)$this->scopeConfig->getValue('design/header/logo_src', ScopeInterface::SCOPE_STORE, $storeId);
        if ($logo !== '') {
            $publisher['logo'] = [
                '@type' => 'ImageObject',
                'url' => $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'logo/' . $logo,
            ];
        }
        return $publisher;
    }

    private function getLanguage(int $storeId): string
    {
        return str_replace('_', '-', (string)$this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId));
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
