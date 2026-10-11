<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use CommerceGears\Blog\Block\AbstractBlog;
use CommerceGears\Blog\Model\Frontend\BlockContext;
use CommerceGears\Blog\Model\Post;

/**
 * "From our blog" on product pages: published posts that list the product as a related product.
 */
class RelatedPosts extends AbstractBlog implements IdentityInterface
{
    /** @var Post[]|null */
    private ?array $posts = null;

    public function __construct(
        Context $context,
        BlockContext $blogContext,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $blogContext, $data);
    }

    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product && $product->getId() ? $product : null;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled() && $this->config->getFlag('product_page/related_posts', $this->getStoreId());
    }

    public function getTitle(): string
    {
        return $this->config->getValue('product_page/title', $this->getStoreId()) ?: (string)__('From our blog');
    }

    /**
     * @return Post[]
     */
    public function getPosts(): array
    {
        if ($this->posts === null) {
            $this->posts = [];
            $product = $this->getProduct();
            if ($product && $this->isEnabled()) {
                $collection = $this->catalog->createPostCollection($this->getStoreId())
                    ->addProductFilter((int)$product->getId());
                $collection->setPageSize($this->config->getInt('product_page/count', 1, 12, 3, $this->getStoreId()))->setCurPage(1);
                $this->posts = array_values($collection->getItems());
            }
        }
        return $this->posts;
    }

    public function getIdentities()
    {
        $product = $this->getProduct();
        if (!$product) {
            return [];
        }
        $tags = [Post::PRODUCT_CACHE_TAG . '_' . $product->getId()];
        foreach ($this->getPosts() as $post) {
            $tags[] = Post::CACHE_TAG . '_' . $post->getId();
        }
        return $tags;
    }
}
