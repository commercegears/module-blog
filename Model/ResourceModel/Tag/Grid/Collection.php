<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\ResourceModel\Tag\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Tags grid with the number of posts.
 */
class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();
        $posts = new \Zend_Db_Expr('(SELECT COUNT(*) FROM ' . $this->getTable('softaware_blog_post_tag')
            . ' pt WHERE pt.tag_id = main_table.tag_id)');
        $this->getSelect()->columns(['posts_count' => $posts]);
        $this->addFilterToMap('posts_count', $posts);
        return $this;
    }
}
