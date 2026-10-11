<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Sitemap;

use Magento\Framework\DataObject;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\SitemapItemInterface;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Psr\Log\LoggerInterface;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\Frontend\Catalog;
use CommerceGears\Blog\Model\Post;
use CommerceGears\Blog\Model\ResourceModel\Author\CollectionFactory as AuthorCollectionFactory;
use CommerceGears\Blog\Model\Url;

/**
 * Adds the blog home page, categories, posts (and optionally tags and authors) to Magento's XML sitemap.
 * Posts and categories set to NOINDEX and posts with a custom canonical URL are left out.
 */
class ItemProvider implements ItemProviderInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly Catalog $catalog,
        private readonly Url $url,
        private readonly SitemapItemInterfaceFactory $itemFactory,
        private readonly AuthorCollectionFactory $authorCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int $storeId
     * @return SitemapItemInterface[]
     */
    public function getItems($storeId)
    {
        $storeId = (int)$storeId;
        if (!$this->config->isEnabled($storeId) || !$this->config->getFlag('sitemap/enabled', $storeId)) {
            return [];
        }
        try {
            return $this->buildItems($storeId);
        } catch (\Throwable $e) {
            $this->logger->error('CommerceGears_Blog sitemap: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return SitemapItemInterface[]
     */
    private function buildItems(int $storeId): array
    {
        $listPriority = (string)$this->config->getValue('sitemap/list_priority', $storeId) ?: '0.4';
        $listFrequency = (string)$this->config->getValue('sitemap/list_frequency', $storeId) ?: 'daily';
        $postPriority = (string)$this->config->getValue('sitemap/post_priority', $storeId) ?: '0.5';
        $postFrequency = (string)$this->config->getValue('sitemap/post_frequency', $storeId) ?: 'weekly';
        $withImages = $this->config->getFlag('sitemap/include_images', $storeId);
        $items = [];
        $latest = null;

        $posts = $this->catalog->createPostCollection($storeId);
        foreach ($posts as $post) {
            /** @var Post $post */
            $updated = (string)($post->getData('updated_at') ?: $post->getPublishAt());
            $latest = $latest === null || $updated > $latest ? $updated : $latest;
            $robots = (string)($post->getData('meta_robots') ?: $this->config->getRobots('post', $storeId));
            if (str_starts_with($robots, 'NOINDEX') || (string)$post->getData('canonical_url') !== '') {
                continue;
            }
            $images = null;
            if ($withImages && $post->getFeaturedImage() !== '') {
                $imageUrl = $this->url->getMediaUrl($post->getFeaturedImage(), $storeId);
                $images = new DataObject([
                    'collection' => [new DataObject([
                        'url' => $imageUrl,
                        'caption' => (string)($post->getData('featured_image_alt') ?: $post->getTitle()),
                    ])],
                    'title' => $post->getTitle(),
                    'thumbnail' => $imageUrl,
                ]);
            }
            $items[] = $this->itemFactory->create([
                'url' => $this->url->getPostPath($post->getUrlKey(), $storeId),
                'priority' => $postPriority,
                'changeFrequency' => $postFrequency,
                'updatedAt' => $updated,
                'images' => $images,
            ]);
        }

        array_unshift($items, $this->itemFactory->create([
            'url' => $this->url->getHomePath($storeId),
            'priority' => $listPriority,
            'changeFrequency' => $listFrequency,
            'updatedAt' => $latest,
        ]));

        foreach ($this->catalog->getCategories($storeId) as $category) {
            $robots = (string)($category->getData('meta_robots') ?: $this->config->getRobots('category', $storeId));
            if (str_starts_with($robots, 'NOINDEX')) {
                continue;
            }
            $items[] = $this->itemFactory->create([
                'url' => $this->url->getEntityPath('category', $category->getUrlKey(), $storeId),
                'priority' => $listPriority,
                'changeFrequency' => $listFrequency,
                'updatedAt' => (string)$category->getData('updated_at'),
            ]);
        }

        if ($this->config->getFlag('sitemap/include_tags', $storeId)) {
            foreach ($this->catalog->getPopularTags($storeId, 5000) as $row) {
                $tag = $row['tag'];
                $robots = (string)($tag->getData('meta_robots') ?: $this->config->getRobots('tag', $storeId));
                if (str_starts_with($robots, 'NOINDEX')) {
                    continue;
                }
                $items[] = $this->itemFactory->create([
                    'url' => $this->url->getEntityPath('tag', $tag->getUrlKey(), $storeId),
                    'priority' => $listPriority,
                    'changeFrequency' => $listFrequency,
                    'updatedAt' => (string)$tag->getData('updated_at'),
                ]);
            }
        }

        if ($this->config->getFlag('sitemap/include_authors', $storeId)
            && !str_starts_with($this->config->getRobots('author', $storeId), 'NOINDEX')
        ) {
            foreach ($this->authorCollectionFactory->create()->addFieldToFilter('is_active', 1) as $author) {
                $items[] = $this->itemFactory->create([
                    'url' => $this->url->getEntityPath('author', $author->getUrlKey(), $storeId),
                    'priority' => $listPriority,
                    'changeFrequency' => $listFrequency,
                    'updatedAt' => (string)$author->getData('updated_at'),
                ]);
            }
        }
        return $items;
    }
}
