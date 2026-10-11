<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\ResourceModel\Category;

use CommerceGears\Blog\Model\Category;
use CommerceGears\Blog\Model\ResourceModel\AbstractContentCollection;
use CommerceGears\Blog\Model\ResourceModel\Category as CategoryResource;

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
