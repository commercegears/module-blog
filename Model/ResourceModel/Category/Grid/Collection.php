<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\ResourceModel\Category\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Categories grid: parent name and number of posts.
 */
class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();
        $posts = new \Zend_Db_Expr('(SELECT COUNT(*) FROM ' . $this->getTable('commercegears_blog_post_category')
            . ' pc WHERE pc.category_id = main_table.category_id)');
        $this->getSelect()
            ->joinLeft(
                ['parent' => $this->getTable('commercegears_blog_category')],
                'parent.category_id = main_table.parent_id',
                ['parent_name' => 'parent.name']
            )
            ->columns(['posts_count' => $posts]);
        $this->addFilterToMap('parent_name', 'parent.name');
        $this->addFilterToMap('posts_count', $posts);
        foreach (['category_id', 'name', 'url_key', 'path', 'level', 'position', 'is_active', 'parent_id', 'created_at', 'updated_at'] as $field) {
            $this->addFilterToMap($field, 'main_table.' . $field);
        }
        return $this;
    }
}
