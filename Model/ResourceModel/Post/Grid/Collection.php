<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\ResourceModel\Post\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Posts grid: author name, state (draft, scheduled, published), store views, categories and comment counts.
 */
class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();
        $connection = $this->getConnection();
        $now = $connection->quote(gmdate('Y-m-d H:i:s'));
        $state = new \Zend_Db_Expr(
            "CASE WHEN main_table.status = 0 THEN 'draft' WHEN main_table.publish_at > " . $now
            . " THEN 'scheduled' ELSE 'published' END"
        );
        $commentTable = $this->getTable('softaware_blog_comment');
        $pending = new \Zend_Db_Expr('(SELECT COUNT(*) FROM ' . $commentTable
            . ' pc WHERE pc.post_id = main_table.post_id AND pc.status = 0)');
        $approved = new \Zend_Db_Expr('(SELECT COUNT(*) FROM ' . $commentTable
            . ' ac WHERE ac.post_id = main_table.post_id AND ac.status = 1)');
        $this->getSelect()
            ->joinLeft(
                ['author' => $this->getTable('softaware_blog_author')],
                'author.author_id = main_table.author_id',
                ['author_name' => 'author.name']
            )
            ->columns([
                'state' => $state,
                'comments_pending' => $pending,
                'comments_approved' => $approved,
                'store_ids' => new \Zend_Db_Expr('(SELECT GROUP_CONCAT(ps.store_id) FROM '
                    . $this->getTable('softaware_blog_post_store') . ' ps WHERE ps.post_id = main_table.post_id)'),
            ]);
        $this->addFilterToMap('state', $state);
        $this->addFilterToMap('author_name', 'author.name');
        $this->addFilterToMap('comments_pending', $pending);
        $this->addFilterToMap('comments_approved', $approved);
        foreach (['post_id', 'title', 'url_key', 'status', 'publish_at', 'is_featured', 'seo_score', 'author_id', 'created_at', 'updated_at'] as $field) {
            $this->addFilterToMap($field, 'main_table.' . $field);
        }
        return $this;
    }

    public function addFieldToFilter($field, $condition = null)
    {
        if ($field === 'store_id') {
            $value = is_array($condition) ? ($condition['eq'] ?? reset($condition)) : $condition;
            $exists = $this->getConnection()->select()
                ->from(['fs' => $this->getTable('softaware_blog_post_store')], [new \Zend_Db_Expr('1')])
                ->where('fs.post_id = main_table.post_id')
                ->where('fs.store_id IN (?)', array_unique([0, (int)$value]));
            $this->getSelect()->where('EXISTS (' . $exists . ')');
            return $this;
        }
        if ($field === 'category_id') {
            $value = is_array($condition) ? ($condition['eq'] ?? reset($condition)) : $condition;
            $exists = $this->getConnection()->select()
                ->from(['fc' => $this->getTable('softaware_blog_post_category')], [new \Zend_Db_Expr('1')])
                ->where('fc.post_id = main_table.post_id')
                ->where('fc.category_id = ?', (int)$value);
            $this->getSelect()->where('EXISTS (' . $exists . ')');
            return $this;
        }
        return parent::addFieldToFilter($field, $condition);
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();
        foreach ($this->_items as $item) {
            $stores = (string)$item->getData('store_ids');
            $item->setData('store_id', $stores === '' ? [0] : array_map('intval', explode(',', $stores)));
        }
        return $this;
    }
}
