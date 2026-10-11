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
use Softaware\Blog\Model\AuthorFactory;
use Softaware\Blog\Model\ImageUploader;
use Softaware\Blog\Model\ResourceModel\Author as AuthorResource;
use Softaware\Blog\Model\ResourceModel\Author\CollectionFactory;

class AuthorFormDataProvider extends AbstractFormDataProvider
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
        private readonly AuthorFactory $authorFactory,
        private readonly AuthorResource $authorResource,
        private readonly ImageUploader $imageUploader,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $request, $dataPersistor, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    protected function loadEntity(int $id, int $storeId): ?AbstractModel
    {
        $author = $this->authorFactory->create();
        $this->authorResource->load($author, $id);
        return $author;
    }

    protected function prepareData(AbstractModel $entity, array $data): array
    {
        $data['photo'] = $this->imageUploader->toUiValue((string)$entity->getData('photo'));
        $data['is_active'] = (string)(int)$entity->getData('is_active');
        return $data;
    }
}
