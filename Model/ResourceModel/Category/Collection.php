<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\ResourceModel\Category;

use Softaware\Blog\Model\Category;
use Softaware\Blog\Model\ResourceModel\AbstractContentCollection;
use Softaware\Blog\Model\ResourceModel\Category as CategoryResource;

class Collection extends AbstractContentCollection
{
    protected $_idFieldName = 'category_id';

    protected function _construct()
    {
        $this->_init(Category::class, CategoryResource::class);
    }

    public function addActiveFilter(): static
    {
        $this->getSelect()->where('main_table.is_active = 1');
        return $this;
    }

    public function orderByTree(): static
    {
        $this->getSelect()->order(['main_table.level ASC', 'main_table.position ASC', 'main_table.name ASC']);
        return $this;
    }
}
