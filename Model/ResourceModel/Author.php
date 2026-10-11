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
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Softaware\Blog\Model\Author as AuthorModel;
use Softaware\Blog\Model\UrlKeyFormatter;

class Author extends AbstractDb
{
    public const TABLE = 'softaware_blog_author';

    public function __construct(
        Context $context,
        private readonly UrlKeyFormatter $urlKeyFormatter,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    protected function _construct()
    {
        $this->_init(self::TABLE, 'author_id');
    }

    protected function _beforeSave(AbstractModel $object)
    {
        /** @var AuthorModel $object */
        $name = trim($object->getName());
        if ($name === '') {
            throw new LocalizedException(__('Please enter a name.'));
        }
        $object->setData('name', $name);
        $urlKey = $this->urlKeyFormatter->format($object->getUrlKey() !== '' ? $object->getUrlKey() : $name);
        if ($urlKey === '') {
            $urlKey = 'author-' . substr(sha1($name), 0, 8);
        }
        $connection = $this->getConnection();
        $taken = $connection->fetchOne(
            $connection->select()->from($this->getMainTable(), ['author_id'])
                ->where('url_key = ?', $urlKey)
                ->where('author_id <> ?', (int)$object->getId())
        );
        if ($taken) {
            throw new LocalizedException(__('Another author already uses the URL key "%1".', $urlKey));
        }
        $object->setData('url_key', $urlKey);
        foreach (['website_url', 'linkedin_url', 'x_url'] as $field) {
            $value = trim((string)$object->getData($field));
            if ($value !== '' && !preg_match('#^https?://#i', $value)) {
                throw new LocalizedException(__('Please enter a full web address starting with https:// for "%1".', $field));
            }
            $object->setData($field, $value !== '' ? $value : null);
        }
        return parent::_beforeSave($object);
    }

    public function findIdByUrlKey(string $urlKey): int
    {
        $connection = $this->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()->from($this->getMainTable(), ['author_id'])->where('url_key = ?', $urlKey)
        );
    }
}
