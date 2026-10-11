<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Ui\DataProvider;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\ImageUploader;
use Softaware\Blog\Model\Post;
use Softaware\Blog\Model\PostFactory;
use Softaware\Blog\Model\ResourceModel\Post as PostResource;
use Softaware\Blog\Model\ResourceModel\Post\CollectionFactory;
use Softaware\Blog\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;
use Softaware\Blog\Model\SeoChecklist;

class PostFormDataProvider extends AbstractFormDataProvider
{
    private const FIELDSETS = [
        'title' => 'general', 'url_key' => 'general',
        'short_content' => 'content', 'content' => 'content', 'featured_image_alt' => 'content',
        'meta_title' => 'seo', 'meta_description' => 'seo', 'meta_keywords' => 'seo', 'meta_robots' => 'seo',
        'focus_keyword' => 'seo',
    ];

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
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource,
        private readonly TagCollectionFactory $tagCollectionFactory,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ImageHelper $imageHelper,
        private readonly ImageUploader $imageUploader,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $request, $dataPersistor, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    protected function loadEntity(int $id, int $storeId): ?AbstractModel
    {
        $post = $this->postFactory->create();
        $post->setStoreId($storeId);
        $this->postResource->load($post, $id);
        return $post;
    }

    protected function getStoreFields(): array
    {
        return Post::STORE_FIELDS;
    }

    protected function getFieldset(string $field): string
    {
        return self::FIELDSETS[$field] ?? 'general';
    }

    protected function prepareData(AbstractModel $entity, array $data): array
    {
        /** @var Post $entity */
        $data['featured_image'] = $this->imageUploader->toUiValue((string)$entity->getFeaturedImage());
        $data['store_ids'] = array_map('strval', $entity->getStoreIds() ?: [0]);
        $data['category_ids'] = array_map('strval', $entity->getCategoryIds());
        $data['author_id'] = $entity->getAuthorId() ? (string)$entity->getAuthorId() : '';
        $data['tag_names'] = $this->getTagNames($entity->getTagIds());
        $data['links']['related'] = $this->getProducts($entity->getProductIds());
        foreach (['is_featured', 'allow_comments', 'status'] as $flag) {
            $data[$flag] = (string)(int)$entity->getData($flag);
        }
        return $data;
    }

    public function getMeta()
    {
        $meta = parent::getMeta();
        $meta['seo_checklist']['children']['checklist']['arguments']['data']['config'] = [
            'ownHosts' => $this->postResource->getStoreHosts(),
            'wordsPerMinute' => $this->config->getWordsPerMinute(),
            'limits' => [
                'titleMin' => SeoChecklist::TITLE_MIN,
                'titleMax' => SeoChecklist::TITLE_MAX,
                'descriptionMin' => SeoChecklist::DESCRIPTION_MIN,
                'descriptionMax' => SeoChecklist::DESCRIPTION_MAX,
                'minWords' => SeoChecklist::MIN_WORDS,
            ],
        ];
        if ($this->storeManager->isSingleStoreMode()) {
            $meta['general']['children']['store_ids']['arguments']['data']['config']['visible'] = false;
        }
        if ($this->getStoreId()) {
            // global settings stay editable, but say that they apply to every store view
            foreach (['status' => 'general', 'publish_at' => 'general', 'is_featured' => 'general', 'allow_comments' => 'general',
                'author_id' => 'general', 'store_ids' => 'general', 'category_ids' => 'general', 'tag_names' => 'general',
                'featured_image' => 'content', 'canonical_url' => 'seo'] as $field => $fieldset) {
                $meta[$fieldset]['children'][$field]['arguments']['data']['config']['scopeLabel'] = __('[GLOBAL]');
            }
        }
        return $meta;
    }

    /**
     * @param int[] $tagIds
     */
    private function getTagNames(array $tagIds): string
    {
        if (!$tagIds) {
            return '';
        }
        $names = [];
        foreach ($this->tagCollectionFactory->create()->addFieldToFilter('tag_id', ['in' => $tagIds])->setOrder('name', 'ASC') as $tag) {
            $names[] = $tag->getName();
        }
        return implode(', ', $names);
    }

    /**
     * @param int[] $productIds
     * @return array<int, array<string, string>>
     */
    private function getProducts(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect(['name', 'thumbnail', 'small_image', 'status'])
            ->addIdFilter($productIds);
        $byId = [];
        foreach ($collection as $product) {
            $byId[(int)$product->getId()] = $product;
        }
        $rows = [];
        foreach ($productIds as $position => $productId) {
            if (!isset($byId[$productId])) {
                continue;
            }
            $product = $byId[$productId];
            $rows[] = [
                'id' => (string)$productId,
                'name' => (string)$product->getName(),
                'sku' => (string)$product->getSku(),
                'status' => (int)$product->getStatus() === 1 ? (string)__('Enabled') : (string)__('Disabled'),
                'thumbnail' => $this->imageHelper->init($product, 'product_listing_thumbnail')->getUrl(),
                'position' => (string)($position + 1),
                'record_id' => (string)$productId,
            ];
        }
        return $rows;
    }
}
