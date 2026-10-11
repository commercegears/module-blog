<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

use CommerceGears\Blog\Model\ResourceModel\Category as CategoryResource;

class Category extends AbstractContentModel
{
    public const CACHE_TAG = 'swb_c';
    public const STORE_FIELDS = ['name', 'url_key', 'description', 'meta_title', 'meta_description', 'meta_keywords', 'meta_robots'];

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'commercegears_blog_category';

    protected function _construct()
    {
        $this->_init(CategoryResource::class);
    }

    public function getIdentities()
    {
        return $this->getId() ? [self::CACHE_TAG, self::CACHE_TAG . '_' . $this->getId()] : [self::CACHE_TAG];
    }

    public function getName(): string
    {
        return (string)$this->getData('name');
    }

    public function getUrlKey(): string
    {
        return (string)$this->getData('url_key');
    }

    public function getParentId(): int
    {
        return (int)$this->getData('parent_id');
    }

    public function isActive(): bool
    {
        return (bool)$this->getData('is_active');
    }

    /**
     * @return int[] ancestor IDs from the top, without this category
     */
    public function getAncestorIds(): array
    {
        $ids = array_map('intval', array_filter(explode('/', (string)$this->getData('path'))));
        return array_values(array_filter($ids, fn ($id) => $id !== (int)$this->getId()));
    }
}
