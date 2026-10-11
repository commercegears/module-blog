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
use Magento\Ui\DataProvider\AbstractDataProvider;
use Softaware\Blog\Model\AbstractContentModel;

/**
 * Edit form data. For entities with store view content, the form opened with ?store=<id> shows the store
 * view's values with a "Use Default Value" checkbox on every field that can differ per store view.
 */
abstract class AbstractFormDataProvider extends AbstractDataProvider
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $loadedData = null;

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        protected readonly RequestInterface $request,
        protected readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * The entity for the form (new, empty model when there is no ID).
     */
    abstract protected function loadEntity(int $id, int $storeId): ?AbstractModel;

    /**
     * @return string[] fields that can differ per store view ([] for global entities)
     */
    protected function getStoreFields(): array
    {
        return [];
    }

    /**
     * Fieldset that holds a field, for the store view meta.
     */
    protected function getFieldset(string $field): string
    {
        return 'general';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareData(AbstractModel $entity, array $data): array
    {
        return $data;
    }

    /**
     * Data persistor key for the form values of a failed save (set by the save controller).
     */
    public function getPersistKey(): string
    {
        return $this->name;
    }

    protected function getStoreId(): int
    {
        return (int)$this->request->getParam('store');
    }

    public function getData()
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        $id = (int)$this->request->getParam($this->requestFieldName);
        // values of a save that failed (shown again with the error message)
        $persisted = $this->dataPersistor->get($this->getPersistKey());
        if (is_array($persisted) && $persisted) {
            $this->dataPersistor->clear($this->getPersistKey());
            $this->loadedData[$id ?: ''] = $persisted;
            return $this->loadedData;
        }
        if (!$id) {
            return $this->loadedData;
        }
        $entity = $this->loadEntity($id, $this->getStoreId());
        if (!$entity || !$entity->getId()) {
            return $this->loadedData;
        }
        $data = $entity->getData();
        unset($data['_store_overrides']);
        $data['store'] = $this->getStoreId();
        if ($this->getStoreId() && $entity instanceof AbstractContentModel) {
            $overrides = $entity->getStoreOverrides();
            foreach ($this->getStoreFields() as $field) {
                $data['use_default'][$field] = in_array($field, $overrides, true) ? '0' : '1';
            }
        }
        $this->loadedData[(int)$entity->getId()] = $this->prepareData($entity, $data);
        return $this->loadedData;
    }

    public function getMeta()
    {
        $meta = parent::getMeta();
        $storeId = $this->getStoreId();
        $id = (int)$this->request->getParam($this->requestFieldName);
        if (!$storeId || !$id || !$this->getStoreFields()) {
            return $meta;
        }
        $entity = $this->loadEntity($id, $storeId);
        $overrides = $entity instanceof AbstractContentModel ? $entity->getStoreOverrides() : [];
        foreach ($this->getStoreFields() as $field) {
            $meta[$this->getFieldset($field)]['children'][$field]['arguments']['data']['config'] = [
                'service' => ['template' => 'ui/form/element/helper/service'],
                'disabled' => !in_array($field, $overrides, true),
                'scopeLabel' => __('[STORE VIEW]'),
            ];
        }
        return $meta;
    }
}
