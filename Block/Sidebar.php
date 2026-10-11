<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block;

use Magento\Framework\DataObject\IdentityInterface;
use CommerceGears\Blog\Model\Category;
use CommerceGears\Blog\Model\Post;
use CommerceGears\Blog\Model\Tag;

/**
 * Sidebar of the blog pages: categories, recent posts, tags and the RSS link.
 */
class Sidebar extends AbstractBlog implements IdentityInterface
{
    public function isEnabled(): bool
    {
        return parent::isEnabled() && $this->config->getFlag('sidebar/enabled', $this->getStoreId());
    }

    /**
     * @return array<int, array{category: Category, depth: int}>
     */
    public function getCategoryTree(): array
    {
        return $this->config->getFlag('sidebar/categories', $this->getStoreId())
            ? $this->catalog->getCategoryTree($this->getStoreId())
            : [];
    }

    public function isCurrentCategory(Category $category): bool
    {
        $current = $this->currentPage->getCategory();
        return $current !== null && (int)$current->getId() === (int)$category->getId();
    }

    /**
     * @return Post[]
     */
    public function getRecentPosts(): array
    {
        $current = $this->currentPage->getPost();
        return $this->catalog->getRecentPosts(
            $this->getStoreId(),
            $this->config->getInt('sidebar/recent_count', 0, 20, 5, $this->getStoreId()),
            $current ? [(int)$current->getId()] : []
        );
    }

    /**
     * @return array<int, array{tag: Tag, count: int}>
     */
    public function getTags(): array
    {
        $limit = $this->config->getInt('sidebar/tags_count', 0, 100, 20, $this->getStoreId());
        if ($limit === 0) {
            return [];
        }
        $tags = $this->catalog->getPopularTags($this->getStoreId(), $limit);
        uasort($tags, static fn ($a, $b) => strcasecmp($a['tag']->getName(), $b['tag']->getName()));
        return $tags;
    }

    public function getRssUrl(): string
    {
        return $this->config->getFlag('rss/enabled', $this->getStoreId())
            ? $this->blogUrl->getRssUrl($this->getStoreId())
            : '';
    }

    public function getIdentities()
    {
        return [Post::CACHE_TAG, Category::CACHE_TAG, Tag::CACHE_TAG];
    }
}
