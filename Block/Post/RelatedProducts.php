<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block\Post;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DataObject\IdentityInterface;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\CurrentPage;
use Softaware\Blog\Model\Frontend\ThemeResolver;

/**
 * Products linked to the post in the admin ("Related Products"), in the chosen order.
 */
class RelatedProducts extends AbstractProduct implements IdentityInterface
{
    /** @var Product[]|null */
    private ?array $products = null;

    public function __construct(
        Context $context,
        private readonly CurrentPage $currentPage,
        private readonly Config $config,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly CatalogConfig $catalogConfig,
        private readonly Visibility $visibility,
        private readonly ThemeResolver $themeResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getTitle(): string
    {
        return $this->config->getValue('post/related_products_title', (int)$this->_storeManager->getStore()->getId())
            ?: (string)__('Products in this post');
    }

    /**
     * @return Product[]
     */
    public function getProducts(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }
        $this->products = [];
        $post = $this->currentPage->getPost();
        $storeId = (int)$this->_storeManager->getStore()->getId();
        $limit = $this->config->getInt('post/related_products', 0, 12, 4, $storeId);
        $ids = $post ? $post->getProductIds() : [];
        if (!$ids || $limit === 0) {
            return $this->products;
        }
        $collection = $this->productCollectionFactory->create()
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect($this->catalogConfig->getProductAttributes())
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->addIdFilter($ids)
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite();
        $byId = [];
        foreach ($collection as $product) {
            $byId[(int)$product->getId()] = $product;
        }
        foreach ($ids as $id) {
            if (isset($byId[$id]) && count($this->products) < $limit) {
                $this->products[] = $byId[$id];
            }
        }
        return $this->products;
    }

    public function isHyva(): bool
    {
        return $this->themeResolver->isHyva();
    }

    public function getIdentities()
    {
        $tags = [];
        foreach ($this->getProducts() as $product) {
            $tags = array_merge($tags, $product->getIdentities());
        }
        return $tags;
    }
}
