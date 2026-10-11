<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\Context;
use CommerceGears\Blog\Model\Category as CategoryModel;
use CommerceGears\Blog\Model\UrlKeyFormatter;

class Category extends AbstractContentResource
{
    public const TABLE = 'commercegears_blog_category';

    public function __construct(
        Context $context,
        private readonly UrlKeyFormatter $urlKeyFormatter,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    protected function _construct()
    {
        $this->_init(self::TABLE, 'category_id');
    }

    public function getContentTableName(): string
    {
        return 'commercegears_blog_category_content';
    }

    public function getStoreFields(): array
    {
        return CategoryModel::STORE_FIELDS;
    }

    protected function _beforeSave(AbstractModel $object)
    {
        /** @var CategoryModel $object */
        $name = trim($object->getName());
        if ($name === '') {
            throw new LocalizedException(__('Please enter a name.'));
        }
        $object->setData('name', $name);
        $urlKey = $this->urlKeyFormatter->format($object->getUrlKey() !== '' ? $object->getUrlKey() : $name);
        if ($urlKey === '') {
            $urlKey = 'category-' . date('YmdHis');
        }
        if ($this->isUrlKeyTaken($urlKey, 0, (int)$object->getId())) {
            throw new LocalizedException(__('Another category already uses the URL key "%1".', $urlKey));
        }
        $object->setData('url_key', $urlKey);

        $parentId = $object->getParentId();
        $object->setData('parent_id', $parentId ?: null);
        if ($parentId && $object->getId()) {
            if ($parentId === (int)$object->getId() || in_array((int)$object->getId(), $this->getPathIds($parentId), true)) {
                throw new LocalizedException(__('A category cannot be placed inside itself or one of its subcategories.'));
            }
        }
        if ($object->getData('meta_robots') === '') {
            $object->setData('meta_robots', null);
        }
        return parent::_beforeSave($object);
    }

    protected function _afterSave(AbstractModel $object)
    {
        /** @var CategoryModel $object */
        $this->updatePath((int)$object->getId());
        $object->setData('path', $this->getConnection()->fetchOne(
            $this->getConnection()->select()->from($this->getMainTable(), ['path'])->where('category_id = ?', (int)$object->getId())
        ));
        foreach ((array)$object->getData('_store_content') as $storeId => $values) {
            $this->saveStoreContent((int)$object->getId(), (int)$storeId, (array)$values);
        }
        $object->unsetData('_store_content');
        return parent::_afterSave($object);
    }

    /**
     * Path "1/4/9" (ancestors and the category) of a category, from the parent chain.
     *
     * @return int[]
     */
    public function getPathIds(int $categoryId): array
    {
        $connection = $this->getConnection();
        $ids = [];
        $current = $categoryId;
        while ($current && !in_array($current, $ids, true) && count($ids) < 50) {
            array_unshift($ids, $current);
            $current = (int)$connection->fetchOne(
                $connection->select()->from($this->getMainTable(), ['parent_id'])->where('category_id = ?', $current)
            );
        }
        return $ids;
    }

    /**
     * Rewrites path and level of the category and all its subcategories.
     */
    public function updatePath(int $categoryId): void
    {
        $connection = $this->getConnection();
        $queue = [$categoryId];
        $guard = 0;
        while ($queue && $guard++ < 10000) {
            $id = array_shift($queue);
            $path = $this->getPathIds($id);
            $connection->update(
                $this->getMainTable(),
                ['path' => implode('/', $path), 'level' => count($path)],
                ['category_id = ?' => $id]
            );
            $children = $connection->fetchCol(
                $connection->select()->from($this->getMainTable(), ['category_id'])->where('parent_id = ?', $id)
            );
            foreach ($children as $child) {
                $queue[] = (int)$child;
            }
        }
    }

    /**
     * @return int[] the category and all its subcategories
     */
    public function getDescendantIds(int $categoryId, bool $activeOnly = false): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['category_id'])
            ->where('category_id = ? OR path LIKE ' . $connection->quote('%/' . $categoryId . '/%')
                . ' OR path LIKE ' . $connection->quote($categoryId . '/%'), $categoryId);
        if ($activeOnly) {
            $select->where('is_active = 1');
        }
        return array_map('intval', $connection->fetchCol($select));
    }
}
