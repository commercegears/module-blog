<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model;

use Softaware\Blog\Api\Data\PostInterface;
use Softaware\Blog\Model\ResourceModel\Post as PostResource;

class Post extends AbstractContentModel implements PostInterface
{
    public const CACHE_TAG = 'swb_p';
    public const PRODUCT_CACHE_TAG = 'swb_pp';
    public const STATUS_DRAFT = 0;
    public const STATUS_PUBLISHED = 1;

    /** Fields that can have a value per store view. */
    public const STORE_FIELDS = [
        'title', 'url_key', 'short_content', 'content', 'featured_image_alt',
        'meta_title', 'meta_description', 'meta_keywords', 'meta_robots', 'focus_keyword',
    ];

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'softaware_blog_post';

    protected function _construct()
    {
        $this->_init(PostResource::class);
    }

    public function getIdentities()
    {
        $tags = [self::CACHE_TAG];
        if ($this->getId()) {
            $tags[] = self::CACHE_TAG . '_' . $this->getId();
        }
        foreach ((array)$this->getData('_affected_product_ids') as $productId) {
            $tags[] = self::PRODUCT_CACHE_TAG . '_' . (int)$productId;
        }
        return $tags;
    }

    public function getTitle(): string
    {
        return (string)$this->getData(self::TITLE);
    }

    public function setTitle(string $title): static
    {
        return $this->setData(self::TITLE, $title);
    }

    public function getUrlKey(): string
    {
        return (string)$this->getData(self::URL_KEY);
    }

    public function setUrlKey(string $urlKey): static
    {
        return $this->setData(self::URL_KEY, $urlKey);
    }

    public function getShortContent(): string
    {
        return (string)$this->getData(self::SHORT_CONTENT);
    }

    public function setShortContent(?string $shortContent): static
    {
        return $this->setData(self::SHORT_CONTENT, $shortContent);
    }

    public function getContent(): string
    {
        return (string)$this->getData(self::CONTENT);
    }

    public function setContent(?string $content): static
    {
        return $this->setData(self::CONTENT, $content);
    }

    public function getFeaturedImage(): string
    {
        return (string)$this->getData(self::FEATURED_IMAGE);
    }

    public function getAuthorId(): ?int
    {
        $value = $this->getData(self::AUTHOR_ID);
        return $value ? (int)$value : null;
    }

    public function setAuthorId(?int $authorId): static
    {
        return $this->setData(self::AUTHOR_ID, $authorId ?: null);
    }

    public function getStatus(): int
    {
        return (int)$this->getData(self::STATUS);
    }

    public function setStatus(int $status): static
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getPublishAt(): ?string
    {
        $value = $this->getData(self::PUBLISH_AT);
        return $value ? (string)$value : null;
    }

    public function setPublishAt(?string $publishAt): static
    {
        return $this->setData(self::PUBLISH_AT, $publishAt ?: null);
    }

    public function isFeatured(): bool
    {
        return (bool)$this->getData(self::IS_FEATURED);
    }

    public function areCommentsAllowed(): bool
    {
        return (bool)$this->getData(self::ALLOW_COMMENTS);
    }

    /**
     * Published and the publish date has passed.
     */
    public function isLive(?int $now = null): bool
    {
        if ($this->getStatus() !== self::STATUS_PUBLISHED) {
            return false;
        }
        $publishAt = $this->getPublishAt();
        return $publishAt === null || strtotime($publishAt . ' UTC') <= ($now ?? time());
    }

    public function isScheduled(?int $now = null): bool
    {
        $publishAt = $this->getPublishAt();
        return $this->getStatus() === self::STATUS_PUBLISHED && $publishAt !== null
            && strtotime($publishAt . ' UTC') > ($now ?? time());
    }

    public function getStoreIds(): array
    {
        return $this->intList(self::STORE_IDS);
    }

    public function setStoreIds(array $storeIds): static
    {
        return $this->setData(self::STORE_IDS, array_values(array_unique(array_map('intval', $storeIds))));
    }

    public function getCategoryIds(): array
    {
        return $this->intList(self::CATEGORY_IDS);
    }

    public function setCategoryIds(array $categoryIds): static
    {
        return $this->setData(self::CATEGORY_IDS, array_values(array_unique(array_map('intval', $categoryIds))));
    }

    public function getTagIds(): array
    {
        return $this->intList(self::TAG_IDS);
    }

    public function setTagIds(array $tagIds): static
    {
        return $this->setData(self::TAG_IDS, array_values(array_unique(array_map('intval', $tagIds))));
    }

    public function getProductIds(): array
    {
        return $this->intList(self::PRODUCT_IDS);
    }

    public function setProductIds(array $productIds): static
    {
        return $this->setData(self::PRODUCT_IDS, array_values(array_unique(array_map('intval', $productIds))));
    }

    /**
     * @return int[]
     */
    private function intList(string $key): array
    {
        $value = $this->getData($key);
        if (is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        }
        return array_values(array_filter(array_map('intval', (array)$value), static fn ($id) => $id >= 0));
    }
}
