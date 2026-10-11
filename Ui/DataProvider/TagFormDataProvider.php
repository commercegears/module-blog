<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Ui\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\AbstractModel;
use Softaware\Blog\Model\ResourceModel\Tag as TagResource;
use Softaware\Blog\Model\ResourceModel\Tag\CollectionFactory;
use Softaware\Blog\Model\Tag;
use Softaware\Blog\Model\TagFactory;

class TagFormDataProvider extends AbstractFormDataProvider
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        RequestInterface $request,
        DataPersistorInterface $dataPersistor,
        CollectionFactory $collectionFactory,
        private readonly TagFactory $tagFactory,
        private readonly TagResource $tagResource,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $request, $dataPersistor, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    protected function loadEntity(int $id, int $storeId): ?AbstractModel
    {
        $tag = $this->tagFactory->create();
        $tag->setStoreId($storeId);
        $this->tagResource->load($tag, $id);
        return $tag;
    }

    protected function getStoreFields(): array
    {
        return Tag::STORE_FIELDS;
    }

    protected function getFieldset(string $field): string
    {
        return in_array($field, ['meta_title', 'meta_description', 'meta_robots'], true) ? 'seo' : 'general';
    }
}
