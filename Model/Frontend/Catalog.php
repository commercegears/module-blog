<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Frontend;

use CommerceGears\Blog\Model\Author;
use CommerceGears\Blog\Model\Category;
use CommerceGears\Blog\Model\Post;
use CommerceGears\Blog\Model\ResourceModel\Author\CollectionFactory as AuthorCollectionFactory;
use CommerceGears\Blog\Model\ResourceModel\Category as CategoryResource;
use CommerceGears\Blog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use CommerceGears\Blog\Model\ResourceModel\Post\Collection as PostCollection;
use CommerceGears\Blog\Model\ResourceModel\Post\CollectionFactory as PostCollectionFactory;
use CommerceGears\Blog\Model\ResourceModel\Tag as TagResource;
use CommerceGears\Blog\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;
use CommerceGears\Blog\Model\Tag;

/**
 * Storefront reads: posts visible in a store view, categories, tags and authors (cached per request).
 */
class Catalog
{
    /** @var array<int, array<int, Category>> */
    private array $categories = [];

    /** @var array<int, Author> */
    private ?array $authors = null;

    public function __construct(
        private readonly PostCollectionFactory $postCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly CategoryResource $categoryResource,
        private readonly TagCollectionFactory $tagCollectionFactory,
        private readonly TagResource $tagResource,
        private readonly AuthorCollectionFactory $authorCollectionFactory
    ) {
    }

    public function createPostCollection(int $storeId): PostCollection
    {
        return $this->postCollectionFactory->create()->forStorefront($storeId);
    }

    /**
     * @return array<int, Category> active categories by ID, ordered as a tree (parents before children)
     */
    public function getCategories(int $storeId): array
    {
        if (!isset($this->categories[$storeId])) {
            $collection = $this->categoryCollectionFactory->create()
                ->joinStoreContent($storeId)
                ->addActiveFilter()
                ->orderByTree();
            $byId = [];
            foreach ($collection as $category) {
                $byId[(int)$category->getId()] = $category;
            }
            // drop categories below an inactive parent
            $this->categories[$storeId] = array_filter(
                $byId,
                static function (Category $category) use ($byId): bool {
                    foreach ($category->getAncestorIds() as $ancestorId) {
                        if (!isset($byId[$ancestorId])) {
                            return false;
                        }
                    }
                    return true;
                }
            );
        }
        return $this->categories[$storeId];
    }

    /**
     * @return array<int, array{category: Category, depth: int}> depth-first order for menus
     */
    public function getCategoryTree(int $storeId): array
    {
        $categories = $this->getCategories($storeId);
        $children = [];
        foreach ($categories as $id => $category) {
            $children[$category->getParentId()][] = $id;
        }
        foreach ($children as &$ids) {
            usort($ids, static fn ($a, $b) => [(int)$categories[$a]->getData('position'), $categories[$a]->getName()]
                <=> [(int)$categories[$b]->getData('position'), $categories[$b]->getName()]);
        }
        unset($ids);
        $result = [];
        $walk = static function (int $parentId, int $depth) use (&$walk, &$result, $children, $categories): void {
            foreach ($children[$parentId] ?? [] as $id) {
                $result[] = ['category' => $categories[$id], 'depth' => $depth];
                $walk($id, $depth + 1);
            }
        };
        $walk(0, 0);
        return $result;
    }

    public function getCategory(int $categoryId, int $storeId): ?Category
    {
        return $this->getCategories($storeId)[$categoryId] ?? null;
    }

    /**
     * @return Category[] from the top level down to the category
     */
    public function getCategoryPath(Category $category, int $storeId): array
    {
        $path = [];
        foreach ($category->getAncestorIds() as $ancestorId) {
            $ancestor = $this->getCategory($ancestorId, $storeId);
            if ($ancestor) {
                $path[] = $ancestor;
            }
        }
        $path[] = $category;
        return $path;
    }

    /**
     * @return int[] the category and its active subcategories
     */
    public function getCategoryIdsWithChildren(Category $category, int $storeId): array
    {
        $active = $this->getCategories($storeId);
        return array_values(array_filter(
            $this->categoryResource->getDescendantIds((int)$category->getId()),
            static fn ($id) => isset($active[$id])
        ));
    }

    /**
     * @param int[] $tagIds
     * @return array<int, Tag>
     */
    public function getTags(array $tagIds, int $storeId): array
    {
        if (!$tagIds) {
            return [];
        }
        $collection = $this->tagCollectionFactory->create()->joinStoreContent($storeId);
        $collection->addFieldToFilter('main_table.tag_id', ['in' => $tagIds]);
        $collection->setOrder('name', 'ASC');
        $result = [];
        foreach ($collection as $tag) {
            $result[(int)$tag->getId()] = $tag;
        }
        return $result;
    }

    /**
     * @return array<int, array{tag: Tag, count: int}> most used tags first
     */
    public function getPopularTags(int $storeId, int $limit): array
    {
        $usage = $this->tagResource->getUsage($storeId, $limit);
        $tags = $this->getTags(array_keys($usage), $storeId);
        $result = [];
        foreach ($usage as $tagId => $count) {
            if (isset($tags[$tagId])) {
                $result[$tagId] = ['tag' => $tags[$tagId], 'count' => $count];
            }
        }
        return $result;
    }

    public function getAuthor(int $authorId): ?Author
    {
        if ($this->authors === null) {
            $this->authors = [];
            foreach ($this->authorCollectionFactory->create()->addFieldToFilter('is_active', 1) as $author) {
                $this->authors[(int)$author->getId()] = $author;
            }
        }
        return $this->authors[$authorId] ?? null;
    }

    /**
     * @return Post[] recent posts
     */
    public function getRecentPosts(int $storeId, int $limit, array $excludeIds = [], array $categoryIds = [], bool $featuredOnly = false): array
    {
        if ($limit <= 0) {
            return [];
        }
        $collection = $this->createPostCollection($storeId)->excludeIds($excludeIds);
        if ($categoryIds) {
            $collection->addCategoryFilter($categoryIds);
        }
        if ($featuredOnly) {
            $collection->addFeaturedFilter();
        }
        $collection->setPageSize($limit)->setCurPage(1);
        return array_values($collection->getItems());
    }

    /**
     * Posts that share tags or categories with the post, most shared first, then newest.
     *
     * @return Post[]
     */
    public function getRelatedPosts(Post $post, int $storeId, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }
        $collection = $this->createPostCollection($storeId)->excludeIds([(int)$post->getId()]);
        $tagIds = $post->getTagIds() ?: [0];
        $categoryIds = $post->getCategoryIds() ?: [0];
        $score = '((SELECT COUNT(*) FROM ' . $collection->getTable('commercegears_blog_post_tag')
            . ' rt WHERE rt.post_id = main_table.post_id AND rt.tag_id IN (' . implode(',', array_map('intval', $tagIds)) . ')) * 2'
            . ' + (SELECT COUNT(*) FROM ' . $collection->getTable('commercegears_blog_post_category')
            . ' rc WHERE rc.post_id = main_table.post_id AND rc.category_id IN (' . implode(',', array_map('intval', $categoryIds)) . ')))';
        $collection->getSelect()
            ->columns(['relevance' => new \Zend_Db_Expr($score)])
            ->where($score . ' > 0')
            ->reset(\Magento\Framework\DB\Select::ORDER)
            ->order(['relevance DESC', 'main_table.publish_at DESC']);
        $collection->setPageSize($limit)->setCurPage(1);
        return array_values($collection->getItems());
    }

    /**
     * Previous (older) and next (newer) posts.
     *
     * @return array{prev: ?Post, next: ?Post}
     */
    public function getNeighbours(Post $post, int $storeId): array
    {
        $publishAt = (string)$post->getPublishAt();
        $id = (int)$post->getId();
        if ($publishAt === '') {
            return ['prev' => null, 'next' => null];
        }
        $older = $this->createPostCollection($storeId);
        $older->getSelect()->where(
            'main_table.publish_at < ? OR (main_table.publish_at = ' . $older->getConnection()->quote($publishAt)
                . ' AND main_table.post_id < ' . $id . ')',
            $publishAt
        );
        $older->setPageSize(1)->setCurPage(1);
        $newer = $this->createPostCollection($storeId);
        $newer->getSelect()
            ->where(
                'main_table.publish_at > ? OR (main_table.publish_at = ' . $newer->getConnection()->quote($publishAt)
                    . ' AND main_table.post_id > ' . $id . ')',
                $publishAt
            )
            ->reset(\Magento\Framework\DB\Select::ORDER)
            ->order(['main_table.publish_at ASC', 'main_table.post_id ASC']);
        $newer->setPageSize(1)->setCurPage(1);
        return [
            'prev' => $older->getFirstItem()->getId() ? $older->getFirstItem() : null,
            'next' => $newer->getFirstItem()->getId() ? $newer->getFirstItem() : null,
        ];
    }

    /**
     * Whether the post may be shown in the store view (published, date passed, assigned to the store view).
     */
    public function isVisible(Post $post, int $storeId): bool
    {
        if (!$post->isLive()) {
            return false;
        }
        $storeIds = $post->getStoreIds();
        return in_array(0, $storeIds, true) || in_array($storeId, $storeIds, true);
    }
}
