<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block\Widget;

use Softaware\Blog\Model\Post;

/**
 * Widget "Softaware Blog: Recent Posts".
 */
class RecentPosts extends AbstractWidget
{
    protected const TEMPLATES = [
        'Softaware_Blog::widget/recent-posts.phtml' => 'Softaware_Blog::hyva/widget/recent-posts.phtml',
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
