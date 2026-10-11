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
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Softaware\Blog\Model\Post as PostModel;
use Softaware\Blog\Model\SeoChecklist;
use Softaware\Blog\Model\UrlKeyFormatter;
use Softaware\Blog\Model\UrlReservedWords;

class Post extends AbstractContentResource
{
    public const TABLE = 'softaware_blog_post';

    public function __construct(
        Context $context,
        private readonly UrlKeyFormatter $urlKeyFormatter,
        private readonly UrlReservedWords $reservedWords,
        private readonly SeoChecklist $seoChecklist,
        private readonly StoreManagerInterface $storeManager,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    protected function _construct()
    {
        $this->_init(self::TABLE, 'post_id');
    }

    public function getContentTableName(): string
    {
        return 'softaware_blog_post_content';
    }

    public function getStoreFields(): array
    {
        return PostModel::STORE_FIELDS;
    }

    protected function _beforeSave(AbstractModel $object)
    {
        /** @var PostModel $object */
        $title = trim($object->getTitle());
        if ($title === '') {
            throw new LocalizedException(__('Please enter a title.'));
        }
        $object->setTitle($title);
        $urlKey = $this->urlKeyFormatter->format($object->getUrlKey() !== '' ? $object->getUrlKey() : $title);
        if ($urlKey === '') {
            $urlKey = 'post-' . date('YmdHis');
        }
        $this->assertUrlKeyAvailable($urlKey, 0, (int)$object->getId());
        $object->setUrlKey($urlKey);

        if ($object->getStatus() === PostModel::STATUS_PUBLISHED && $object->getPublishAt() === null) {
            $object->setPublishAt(gmdate('Y-m-d H:i:s'));
        }
        $object->setData('schedule_pending', $object->isScheduled() ? 1 : 0);
        foreach (['author_id', 'canonical_url', 'meta_robots', 'featured_image'] as $field) {
            if ($object->getData($field) === '') {
                $object->setData($field, null);
            }
        }
        $object->setData('seo_score', $this->seoChecklist->analyse($object->getData(), $this->getStoreHosts())['score']);
        return parent::_beforeSave($object);
    }

    /**
     * @return string[] host names of all store views (links to them are internal links)
     */
    public function getStoreHosts(): array
    {
        $hosts = [];
        foreach ($this->storeManager->getStores() as $store) {
            $host = (string)parse_url((string)$store->getBaseUrl(UrlInterface::URL_TYPE_LINK), PHP_URL_HOST);
            if ($host !== '') {
                $hosts[$host] = $host;
            }
        }
        return array_values($hosts);
    }

    /**
     * @throws LocalizedException
     */
    public function assertUrlKeyAvailable(string $urlKey, int $storeId, int $postId): void
    {
        if ($this->reservedWords->isReserved($urlKey, $storeId)) {
            throw new LocalizedException(__('The URL key "%1" is used by the blog itself. Please choose another one.', $urlKey));
        }
        if ($this->isUrlKeyTaken($urlKey, $storeId, $postId)) {
            throw new LocalizedException(__('Another post already uses the URL key "%1". Please choose another one.', $urlKey));
        }
    }

    protected function _afterSave(AbstractModel $object)
    {
        /** @var PostModel $object */
        $postId = (int)$object->getId();
        if ($object->hasData(PostModel::STORE_IDS)) {
            $storeIds = $object->getStoreIds() ?: [0];
            if (in_array(0, $storeIds, true)) {
                $storeIds = [0];
            }
            $this->replaceLinks('softaware_blog_post_store', $postId, 'store_id', $storeIds);
        }
        if ($object->hasData(PostModel::CATEGORY_IDS)) {
            $this->replaceLinks('softaware_blog_post_category', $postId, 'category_id', array_filter($object->getCategoryIds()));
        }
        if ($object->hasData(PostModel::TAG_IDS)) {
            $this->replaceLinks('softaware_blog_post_tag', $postId, 'tag_id', array_filter($object->getTagIds()));
        }
        if ($object->hasData(PostModel::PRODUCT_IDS)) {
            $old = $this->getLinkIds('softaware_blog_post_product', $postId, 'product_id');
            $new = array_values(array_filter($object->getProductIds()));
            $this->saveProductLinks($postId, $new);
            $object->setData('_affected_product_ids', array_unique(array_merge($old, $new)));
        }
        foreach ((array)$object->getData('_store_content') as $storeId => $values) {
            $this->saveStoreContent($postId, (int)$storeId, (array)$values);
        }
        $object->unsetData('_store_content');
        return parent::_afterSave($object);
    }

    protected function _afterLoad(AbstractModel $object)
    {
        /** @var PostModel $object */
        if ($object->getId()) {
            $postId = (int)$object->getId();
            $object->setData(PostModel::STORE_IDS, $this->getLinkIds('softaware_blog_post_store', $postId, 'store_id'));
            $object->setData(PostModel::CATEGORY_IDS, $this->getLinkIds('softaware_blog_post_category', $postId, 'category_id'));
            $object->setData(PostModel::TAG_IDS, $this->getLinkIds('softaware_blog_post_tag', $postId, 'tag_id'));
            $object->setData(PostModel::PRODUCT_IDS, $this->getProductIds($postId));
        }
        return parent::_afterLoad($object);
    }

    protected function _beforeDelete(AbstractModel $object)
    {
        if ($object->getId()) {
            $object->setData('_affected_product_ids', $this->getProductIds((int)$object->getId()));
        }
        return parent::_beforeDelete($object);
    }

    /**
     * @return int[]
     */
    public function getLinkIds(string $table, int $postId, string $column): array
    {
        $connection = $this->getConnection();
        return array_map('intval', $connection->fetchCol(
            $connection->select()->from($this->getTable($table), [$column])->where('post_id = ?', $postId)
        ));
    }

    /**
     * @return int[] in display order
     */
    public function getProductIds(int $postId): array
    {
        $connection = $this->getConnection();
        return array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($this->getTable('softaware_blog_post_product'), ['product_id'])
                ->where('post_id = ?', $postId)
                ->order(['position ASC', 'product_id ASC'])
        ));
    }

    /**
     * @param int[] $ids
     */
    private function replaceLinks(string $table, int $postId, string $column, array $ids): void
    {
        $connection = $this->getConnection();
        $table = $this->getTable($table);
        $connection->delete($table, ['post_id = ?' => $postId]);
        $rows = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $rows[] = ['post_id' => $postId, $column => $id];
        }
        if ($rows) {
            $connection->insertMultiple($table, $rows);
        }
    }

    /**
     * @param int[] $productIds
     */
    private function saveProductLinks(int $postId, array $productIds): void
    {
        $connection = $this->getConnection();
        $table = $this->getTable('softaware_blog_post_product');
        $connection->delete($table, ['post_id = ?' => $postId]);
        if (!$productIds) {
            return;
        }
        $existing = array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($this->getTable('catalog_product_entity'), ['entity_id'])
                ->where('entity_id IN (?)', $productIds)
        ));
        $rows = [];
        foreach ($productIds as $position => $productId) {
            if (in_array($productId, $existing, true)) {
                $rows[] = ['post_id' => $postId, 'product_id' => $productId, 'position' => $position];
            }
        }
        if ($rows) {
            $connection->insertMultiple($table, $rows);
        }
    }

    /**
     * Posts whose scheduled publish date has passed and that still wait for their caches to be refreshed.
     *
     * @return int[]
     */
    public function getDueScheduledIds(string $nowUtc): array
    {
        $connection = $this->getConnection();
        return array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($this->getMainTable(), ['post_id'])
                ->where('schedule_pending = 1')
                ->where('publish_at <= ?', $nowUtc)
        ));
    }

    /**
     * @param int[] $postIds
     */
    public function clearSchedulePending(array $postIds): void
    {
        if ($postIds) {
            $this->getConnection()->update($this->getMainTable(), ['schedule_pending' => 0], ['post_id IN (?)' => $postIds]);
        }
    }
}
