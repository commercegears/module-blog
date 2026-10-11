<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block\Widget;

use CommerceGears\Blog\Model\Post;

/**
 * Widget "CommerceGears Blog: Recent Posts".
 */
class RecentPosts extends AbstractWidget
{
    protected const TEMPLATES = [
        'CommerceGears_Blog::widget/recent-posts.phtml' => 'CommerceGears_Blog::hyva/widget/recent-posts.phtml',
    ];

    /**
     * @return Post[]
     */
    public function getPosts(): array
    {
        $storeId = $this->getStoreId();
        $categoryIds = [];
        $categoryId = (int)$this->getData('category_id');
        if ($categoryId) {
            $category = $this->catalog->getCategory($categoryId, $storeId);
            if (!$category) {
                return [];
            }
            $categoryIds = $this->catalog->getCategoryIdsWithChildren($category, $storeId);
        }
        $count = max(1, min(24, (int)($this->getData('posts_count') ?: 3)));
        return $this->catalog->getRecentPosts($storeId, $count, [], $categoryIds, (bool)$this->getData('featured_only'));
    }

    public function showImage(): bool
    {
        return $this->getData('show_image') === null || (bool)$this->getData('show_image');
    }

    public function showExcerpt(): bool
    {
        return $this->getData('show_excerpt') === null || (bool)$this->getData('show_excerpt');
    }
}
