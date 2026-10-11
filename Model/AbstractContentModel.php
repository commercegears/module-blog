<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Model whose text fields can differ per store view (see AbstractContentResource).
 *
 * Load with setStoreId() first to get the store view's values; getDefaultValues() keeps the default values
 * the store view inherits.
 */
abstract class AbstractContentModel extends AbstractModel implements IdentityInterface
{
    private int $storeId = 0;

    /** @var array<string, mixed> */
    private array $defaultValues = [];

    public function setStoreId(int $storeId): static
    {
        $this->storeId = $storeId;
        return $this;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    /**
     * @param array<string, string> $overrides
     */
    public function applyStoreContent(array $overrides): void
    {
        foreach ($overrides as $field => $value) {
            if (!array_key_exists($field, $this->defaultValues)) {
                $this->defaultValues[$field] = $this->getData($field);
            }
            $this->setData($field, $value);
        }
        $this->setData('_store_overrides', array_keys($overrides));
    }

    /**
     * @return string[] fields that have a store view value
     */
    public function getStoreOverrides(): array
    {
        return (array)$this->getData('_store_overrides');
    }

    public function getDefaultValue(string $field): mixed
    {
        return array_key_exists($field, $this->defaultValues) ? $this->defaultValues[$field] : $this->getData($field);
    }
}
