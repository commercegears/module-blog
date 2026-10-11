<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Api\Data;

/**
 * Blog post. Text fields return the values of the store view the post was loaded for.
 */
interface PostInterface
{
    public const POST_ID = 'post_id';
    public const TITLE = 'title';
    public const URL_KEY = 'url_key';
    public const SHORT_CONTENT = 'short_content';
    public const CONTENT = 'content';
    public const FEATURED_IMAGE = 'featured_image';
    public const FEATURED_IMAGE_ALT = 'featured_image_alt';
    public const AUTHOR_ID = 'author_id';
    public const STATUS = 'status';
    public const PUBLISH_AT = 'publish_at';
    public const IS_FEATURED = 'is_featured';
    public const ALLOW_COMMENTS = 'allow_comments';
    public const META_TITLE = 'meta_title';
    public const META_DESCRIPTION = 'meta_description';
    public const META_KEYWORDS = 'meta_keywords';
    public const META_ROBOTS = 'meta_robots';
    public const CANONICAL_URL = 'canonical_url';
    public const FOCUS_KEYWORD = 'focus_keyword';
    public const STORE_IDS = 'store_ids';
    public const CATEGORY_IDS = 'category_ids';
    public const TAG_IDS = 'tag_ids';
    public const PRODUCT_IDS = 'product_ids';

    /**
     * @return int|null
     */
    public function getId();

    public function getTitle(): string;

    public function setTitle(string $title): static;

    public function getUrlKey(): string;

    public function setUrlKey(string $urlKey): static;

    public function getShortContent(): string;

    public function setShortContent(?string $shortContent): static;

    public function getContent(): string;

    public function setContent(?string $content): static;

    public function getFeaturedImage(): string;

    public function getAuthorId(): ?int;

    public function setAuthorId(?int $authorId): static;

    public function getStatus(): int;

    public function setStatus(int $status): static;

    /**
     * Publish date in UTC (Y-m-d H:i:s), NULL until first published.
     */
    public function getPublishAt(): ?string;

    public function setPublishAt(?string $publishAt): static;

    public function isFeatured(): bool;

    /**
     * @return int[] store view IDs; 0 = all store views
     */
    public function getStoreIds(): array;

    /**
     * @param int[] $storeIds
     */
    public function setStoreIds(array $storeIds): static;

    /**
     * @return int[]
     */
    public function getCategoryIds(): array;

    /**
     * @param int[] $categoryIds
     */
    public function setCategoryIds(array $categoryIds): static;

    /**
     * @return int[]
     */
    public function getTagIds(): array;

    /**
     * @param int[] $tagIds
     */
    public function setTagIds(array $tagIds): static;

    /**
     * @return int[] related product IDs in display order
     */
    public function getProductIds(): array;

    /**
     * @param int[] $productIds
     */
    public function setProductIds(array $productIds): static;
}
