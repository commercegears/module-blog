<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Softaware\Blog\Model\Tag as TagModel;
use Softaware\Blog\Model\UrlKeyFormatter;

class Tag extends AbstractContentResource
{
    public const TABLE = 'softaware_blog_tag';

    public function __construct(
        Context $context,
        private readonly UrlKeyFormatter $urlKeyFormatter,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    protected function _construct()
    {
        $this->_init(self::TABLE, 'tag_id');
    }

    public function getContentTableName(): string
    {
        return 'softaware_blog_tag_content';
    }

    public function getStoreFields(): array
    {
        return TagModel::STORE_FIELDS;
    }

    protected function _beforeSave(AbstractModel $object)
    {
        /** @var TagModel $object */
        $name = trim($object->getName());
        if ($name === '') {
            throw new LocalizedException(__('Please enter a name.'));
        }
        $object->setData('name', $name);
        $urlKey = $this->urlKeyFormatter->format($object->getUrlKey() !== '' ? $object->getUrlKey() : $name);
        if ($urlKey === '') {
            $urlKey = 'tag-' . substr(sha1($name), 0, 8);
        }
        if ($this->isUrlKeyTaken($urlKey, 0, (int)$object->getId())) {
            throw new LocalizedException(__('Another tag already uses the URL key "%1".', $urlKey));
        }
        $object->setData('url_key', $urlKey);
        if ($object->getData('meta_robots') === '') {
            $object->setData('meta_robots', null);
        }
        return parent::_beforeSave($object);
    }

    protected function _afterSave(AbstractModel $object)
    {
        foreach ((array)$object->getData('_store_content') as $storeId => $values) {
            $this->saveStoreContent((int)$object->getId(), (int)$storeId, (array)$values);
        }
        $object->unsetData('_store_content');
        return parent::_afterSave($object);
    }

    /**
     * Finds tags by name (case-insensitive) and creates the missing ones.
     *
     * @param string[] $names
     * @return int[]
     */
    public function resolveNames(array $names, callable $create): array
    {
        $ids = [];
        $connection = $this->getConnection();
        foreach ($names as $name) {
            $name = trim((string)$name);
            if ($name === '') {
                continue;
            }
            $id = (int)$connection->fetchOne(
                $connection->select()->from($this->getMainTable(), ['tag_id'])
                    ->where('LOWER(name) = ?', mb_strtolower($name))
                    ->limit(1)
            );
            $ids[] = $id ?: (int)$create($name);
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Tags with the number of live posts in the store view, most used first.
     *
     * @return array<int, int> tag_id => post count
     */
    public function getUsage(int $storeId, int $limit): array
    {
        $connection = $this->getConnection();
        $storeExists = $connection->select()
            ->from(['ps' => $this->getTable('softaware_blog_post_store')], [new \Zend_Db_Expr('1')])
            ->where('ps.post_id = p.post_id')
            ->where('ps.store_id IN (?)', array_unique([0, $storeId]));
        $select = $connection->select()
            ->from(['pt' => $this->getTable('softaware_blog_post_tag')], ['tag_id', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->join(['p' => $this->getTable('softaware_blog_post')], 'p.post_id = pt.post_id', [])
            ->where('p.status = 1')
            ->where('p.publish_at IS NULL OR p.publish_at <= ?', gmdate('Y-m-d H:i:s'))
            ->where('EXISTS (' . $storeExists . ')')
            ->group('pt.tag_id')
            ->order(['cnt DESC', 'pt.tag_id ASC'])
            ->limit($limit);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['tag_id']] = (int)$row['cnt'];
        }
        return $result;
    }
}
