<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use CommerceGears\Blog\Model\AbstractContentModel;

/**
 * Collection that can read the values of one store view (store view value, else default value).
 */
abstract class AbstractContentCollection extends AbstractCollection
{
    private int $contentStoreId = 0;

    public function joinStoreContent(int $storeId): static
    {
        $this->contentStoreId = $storeId;
        if ($storeId === 0) {
            return $this;
        }
        /** @var AbstractContentResource $resource */
        $resource = $this->getResource();
        $idField = $resource->getIdFieldName();
        $this->getSelect()->joinLeft(
            ['store_content' => $this->getTable($resource->getContentTableName())],
            'store_content.' . $idField . ' = main_table.' . $idField
                . ' AND store_content.store_id = ' . $storeId,
            []
        );
        $columns = [];
        foreach ($resource->getStoreFields() as $field) {
            $expression = 'IFNULL(store_content.' . $field . ', main_table.' . $field . ')';
            $columns[$field] = new \Zend_Db_Expr($expression);
            $this->addFilterToMap($field, new \Zend_Db_Expr($expression));
        }
        $this->getSelect()->columns($columns);
        return $this;
    }

    public function getContentStoreId(): int
    {
        return $this->contentStoreId;
    }

    protected function _afterLoad()
    {
        foreach ($this->_items as $item) {
            if ($item instanceof AbstractContentModel) {
                $item->setStoreId($this->contentStoreId);
            }
        }
        return parent::_afterLoad();
    }
}
