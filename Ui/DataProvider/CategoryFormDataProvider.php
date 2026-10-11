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
use CommerceGears\Blog\Model\Category;
use CommerceGears\Blog\Model\CategoryFactory;
use CommerceGears\Blog\Model\ResourceModel\Category as CategoryResource;
use CommerceGears\Blog\Model\ResourceModel\Category\CollectionFactory;
use CommerceGears\Blog\Model\Source\CategoryOptions;

class CategoryFormDataProvider extends AbstractFormDataProvider
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
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryResource $categoryResource,
        private readonly CategoryOptions $categoryOptions,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $request, $dataPersistor, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    protected function loadEntity(int $id, int $storeId): ?AbstractModel
    {
        $category = $this->categoryFactory->create();
        $category->setStoreId($storeId);
        $this->categoryResource->load($category, $id);
        return $category;
    }

    protected function getStoreFields(): array
    {
        return Category::STORE_FIELDS;
    }

    protected function getFieldset(string $field): string
    {
        return in_array($field, ['meta_title', 'meta_description', 'meta_keywords', 'meta_robots'], true) ? 'seo' : 'general';
    }

    protected function prepareData(AbstractModel $entity, array $data): array
    {
        $data['parent_id'] = $entity->getData('parent_id') ? (string)$entity->getData('parent_id') : '';
        $data['is_active'] = (string)(int)$entity->getData('is_active');
        return $data;
    }

    public function getMeta()
    {
        $meta = parent::getMeta();
        $id = (int)$this->request->getParam($this->requestFieldName);
        $options = array_merge(
            [['value' => '', 'label' => __('None (top level)')]],
            $this->categoryOptions->getTreeOptions($id)
        );
        $meta['general']['children']['parent_id']['arguments']['data']['config']['options'] = $options;
        return $meta;
    }
}
