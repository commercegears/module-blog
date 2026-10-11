<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block\Widget;

use CommerceGears\Blog\Model\Category;

/**
 * Widget "CommerceGears Blog: Categories".
 */
class Categories extends AbstractWidget
{
    protected const TEMPLATES = [
        'CommerceGears_Blog::widget/categories.phtml' => 'CommerceGears_Blog::hyva/widget/categories.phtml',
    ];

    /**
     * @return array<int, array{category: Category, depth: int}>
     */
    public function getCategoryTree(): array
    {
        $tree = $this->catalog->getCategoryTree($this->getStoreId());
        $maxDepth = (int)$this->getData('max_depth');
        if ($maxDepth > 0) {
            $tree = array_values(array_filter($tree, static fn ($row) => $row['depth'] < $maxDepth));
        }
        return $tree;
    }
}
