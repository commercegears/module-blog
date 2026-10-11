<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\ResourceModel\Post;

use Softaware\Blog\Model\Post;
use Softaware\Blog\Model\ResourceModel\AbstractContentCollection;
use Softaware\Blog\Model\ResourceModel\Post as PostResource;

class Collection extends AbstractContentCollection
{
    protected $_idFieldName = 'post_id';
    protected $_eventPrefix = 'softaware_blog_post_collection';
    protected $_eventObject = 'post_collection';

    private bool $withCategoryIds = false;

    protected function _construct()
    {
        $this->_init(Post::class, PostResource::class);
    }

    /**
     * Posts visible in the store view (assigned to it or to all store views).
     */
    public function addStoreFilter(int $storeId): static
    {
        $exists = $this->getConnection()->select()
            ->from(['ps' => $this->getTable('softaware_blog_post_store')], [new \Zend_Db_Expr('1')])
            ->where('ps.post_id = main_table.post_id')
            ->where('ps.store_id IN (?)', array_unique([0, $storeId]));
        $this->getSelect()->where('EXISTS (' . $exists . ')');
        return $this;
    }

    /**
     * Published posts whose publish date has passed.
     */
    public function addLiveFilter(?string $nowUtc = null): static
    {
        $this->getSelect()
            ->where('main_table.status = ?', Post::STATUS_PUBLISHED)
            ->where('main_table.publish_at IS NULL OR main_table.publish_at <= ?', $nowUtc ?? gmdate('Y-m-d H:i:s'));
        return $this;
    }

    /**
     * Shortcut for storefront lists: visible in the store view, live, store view values.
     */
    public function forStorefront(int $storeId): static
    {
        return $this->addStoreFilter($storeId)->addLiveFilter()->joinStoreContent($storeId)->orderByPublishDate();
    }

    public function orderByPublishDate(): static
    {
        $this->getSelect()->order(['main_table.publish_at DESC', 'main_table.post_id DESC']);
        return $this;
    }

    /**
     * @param int[] $categoryIds
     */
    public function addCategoryFilter(array $categoryIds): static
    {
        return $this->addLinkFilter('softaware_blog_post_category', 'category_id', $categoryIds ?: [0]);
    }

    public function addTagFilter(int $tagId): static
    {
        return $this->addLinkFilter('softaware_blog_post_tag', 'tag_id', [$tagId]);
    }

    public function addProductFilter(int $productId): static
    {
        return $this->addLinkFilter('softaware_blog_post_product', 'product_id', [$productId]);
    }

    public function addAuthorFilter(int $authorId): static
    {
        $this->getSelect()->where('main_table.author_id = ?', $authorId);
        return $this;
    }

    public function addFeaturedFilter(): static
    {
        $this->getSelect()->where('main_table.is_featured = 1');
        return $this;
    }

    /**
     * @param int[] $postIds
     */
    public function excludeIds(array $postIds): static
    {
        if ($postIds) {
            $this->getSelect()->where('main_table.post_id NOT IN (?)', $postIds);
        }
        return $this;
    }

    /**
     * Loads category_ids of every item with one query.
     */
    public function withCategoryIds(): static
    {
        $this->withCategoryIds = true;
        return $this;
    }

    /**
     * @param int[] $ids
     */
    private function addLinkFilter(string $table, string $column, array $ids): static
    {
        $exists = $this->getConnection()->select()
            ->from(['link' => $this->getTable($table)], [new \Zend_Db_Expr('1')])
            ->where('link.post_id = main_table.post_id')
            ->where('link.' . $column . ' IN (?)', array_map('intval', $ids));
        $this->getSelect()->where('EXISTS (' . $exists . ')');
        return $this;
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();
        if ($this->withCategoryIds && $this->_items) {
            $rows = $this->getConnection()->fetchAll(
                $this->getConnection()->select()
                    ->from($this->getTable('softaware_blog_post_category'), ['post_id', 'category_id'])
                    ->where('post_id IN (?)', array_keys($this->_items))
            );
            $map = [];
            foreach ($rows as $row) {
                $map[(int)$row['post_id']][] = (int)$row['category_id'];
            }
            foreach ($this->_items as $id => $item) {
                $item->setData(Post::CATEGORY_IDS, $map[(int)$id] ?? []);
            }
        }
        return $this;
    }
}
