<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\ResourceModel\Tag\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Tags grid with the number of posts.
 */
class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();
        $posts = new \Zend_Db_Expr('(SELECT COUNT(*) FROM ' . $this->getTable('commercegears_blog_post_tag')
            . ' pt WHERE pt.tag_id = main_table.tag_id)');
        $this->getSelect()->columns(['posts_count' => $posts]);
        $this->addFilterToMap('posts_count', $posts);
        return $this;
    }
}
