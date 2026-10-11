<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block\Widget;

use Softaware\Blog\Model\Category;

/**
 * Widget "Softaware Blog: Categories".
 */
class Categories extends AbstractWidget
{
    protected const TEMPLATES = [
        'Softaware_Blog::widget/categories.phtml' => 'Softaware_Blog::hyva/widget/categories.phtml',
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
