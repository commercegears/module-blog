<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Ui\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\AbstractModel;
use CommerceGears\Blog\Model\AuthorFactory;
use CommerceGears\Blog\Model\ImageUploader;
use CommerceGears\Blog\Model\ResourceModel\Author as AuthorResource;
use CommerceGears\Blog\Model\ResourceModel\Author\CollectionFactory;

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
