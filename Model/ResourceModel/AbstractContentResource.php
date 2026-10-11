<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Softaware\Blog\Model\AbstractContentModel;

/**
 * Entity with per-store-view content: a "<main>_content" table holds one row per entity and store view with
 * nullable columns; NULL means "use the default value" of the main table.
 */
abstract class AbstractContentResource extends AbstractDb
{
    abstract public function getContentTableName(): string;

    /**
     * @return string[] columns that can differ per store view
     */
    abstract public function getStoreFields(): array;

    /**
     * @return array<string, string> non-null overrides of one store view
     */
    public function loadStoreContent(int $entityId, int $storeId): array
    {
        if ($storeId === 0 || $entityId === 0) {
            return [];
        }
        $connection = $this->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->getTable($this->getContentTableName()), $this->getStoreFields())
                ->where($this->getIdFieldName() . ' = ?', $entityId)
                ->where('store_id = ?', $storeId)
        );
        return $row ? array_filter($row, static fn ($value) => $value !== null) : [];
    }

    /**
     * @return array<int, array<string, string|null>> store_id => row
     */
    public function loadAllStoreContent(int $entityId): array
    {
        $connection = $this->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->getTable($this->getContentTableName()), array_merge(['store_id'], $this->getStoreFields()))
                ->where($this->getIdFieldName() . ' = ?', $entityId)
        );
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['store_id']] = $row;
        }
        return $result;
    }

    /**
     * @param array<string, string|null> $values field => value, NULL = use default
     */
    public function saveStoreContent(int $entityId, int $storeId, array $values): void
    {
        if ($storeId === 0) {
            return;
        }
        $row = [];
        foreach ($this->getStoreFields() as $field) {
            $value = $values[$field] ?? null;
            $row[$field] = $value === null ? null : (string)$value;
        }
        $connection = $this->getConnection();
        $table = $this->getTable($this->getContentTableName());
        $where = [$this->getIdFieldName() . ' = ?' => $entityId, 'store_id = ?' => $storeId];
        if (!array_filter($row, static fn ($value) => $value !== null)) {
            $connection->delete($table, $where);
            return;
        }
        $connection->insertOnDuplicate(
            $table,
            array_merge([$this->getIdFieldName() => $entityId, 'store_id' => $storeId], $row),
            $this->getStoreFields()
        );
    }

    /**
     * True when another entity already uses the URL key in the store view (0 = default values).
     */
    public function isUrlKeyTaken(string $urlKey, int $storeId, int $excludeId = 0): bool
    {
        $connection = $this->getConnection();
        $idField = $this->getIdFieldName();
        $contentTable = $this->getTable($this->getContentTableName());
        if ($storeId > 0) {
            $override = $connection->select()
                ->from($contentTable, [$idField])
                ->where('store_id = ?', $storeId)
                ->where('url_key = ?', $urlKey)
                ->where($idField . ' <> ?', $excludeId)
                ->limit(1);
            if ($connection->fetchOne($override)) {
                return true;
            }
        }
        $select = $connection->select()
            ->from(['main' => $this->getMainTable()], [$idField])
            ->where('main.url_key = ?', $urlKey)
            ->where('main.' . $idField . ' <> ?', $excludeId)
            ->limit(1);
        if ($storeId > 0) {
            $select->joinLeft(
                ['c' => $contentTable],
                'c.' . $idField . ' = main.' . $idField . ' AND c.store_id = ' . $storeId,
                []
            )->where('c.url_key IS NULL');
        }
        return (bool)$connection->fetchOne($select);
    }

    /**
     * Entity ID for a URL key in a store view: the store view's own key wins over the default key.
     */
    public function findIdByUrlKey(string $urlKey, int $storeId): int
    {
        $connection = $this->getConnection();
        $idField = $this->getIdFieldName();
        $contentTable = $this->getTable($this->getContentTableName());
        if ($storeId > 0) {
            $id = (int)$connection->fetchOne(
                $connection->select()
                    ->from($contentTable, [$idField])
                    ->where('store_id = ?', $storeId)
                    ->where('url_key = ?', $urlKey)
                    ->limit(1)
            );
            if ($id) {
                return $id;
            }
        }
        $select = $connection->select()
            ->from(['main' => $this->getMainTable()], [$idField])
            ->joinLeft(
                ['c' => $contentTable],
                'c.' . $idField . ' = main.' . $idField . ' AND c.store_id = ' . $storeId,
                []
            )
            ->where('main.url_key = ?', $urlKey)
            ->where('c.url_key IS NULL')
            ->limit(1);
        return (int)$connection->fetchOne($select);
    }

    protected function _afterLoad(AbstractModel $object)
    {
        if ($object instanceof AbstractContentModel && $object->getStoreId() > 0 && $object->getId()) {
            $object->applyStoreContent($this->loadStoreContent((int)$object->getId(), $object->getStoreId()));
        }
        return parent::_afterLoad($object);
    }
}
