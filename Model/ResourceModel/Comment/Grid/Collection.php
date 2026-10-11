<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\ResourceModel\Comment\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Comments grid with the post title.
 */
class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['post' => $this->getTable('softaware_blog_post')],
            'post.post_id = main_table.post_id',
            ['post_title' => 'post.title']
        );
        $this->addFilterToMap('post_title', 'post.title');
        foreach (['comment_id', 'post_id', 'status', 'author_name', 'author_email', 'content', 'store_id', 'created_at', 'is_admin_reply', 'parent_id'] as $field) {
            $this->addFilterToMap($field, 'main_table.' . $field);
        }
        return $this;
    }
}
