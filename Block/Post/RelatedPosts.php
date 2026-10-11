<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block\Post;

use Magento\Framework\DataObject\IdentityInterface;
use Softaware\Blog\Block\AbstractBlog;
use Softaware\Blog\Model\Post;

/**
 * Posts sharing tags or categories with the current post.
 */
class RelatedPosts extends AbstractBlog implements IdentityInterface
{
    /** @var Post[]|null */
    private ?array $posts = null;

    /**
     * @return Post[]
     */
    public function getPosts(): array
    {
        if ($this->posts === null) {
            $post = $this->currentPage->getPost();
            $this->posts = $post && !$this->currentPage->isPreview()
                ? $this->catalog->getRelatedPosts(
                    $post,
                    $this->getStoreId(),
                    $this->config->getInt('post/related_posts', 0, 12, 3, $this->getStoreId())
                )
                : [];
        }
        return $this->posts;
    }

    public function getIdentities()
    {
        return [Post::CACHE_TAG];
    }
}
