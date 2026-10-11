<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Theme\Block\Html\Pager;
use Softaware\Blog\Model\Author;
use Softaware\Blog\Model\Category;
use Softaware\Blog\Model\CurrentPage;
use Softaware\Blog\Model\Post;
use Softaware\Blog\Model\ResourceModel\Post\Collection;
use Softaware\Blog\Model\Tag;

/**
 * Post list of the blog home, category, tag and author pages.
 */
class PostList extends AbstractBlog implements IdentityInterface
{
    private ?Collection $collection = null;

    /** @var Post[]|null */
    private ?array $featured = null;

    public function getCollection(): Collection
    {
        if ($this->collection === null) {
            $storeId = $this->getStoreId();
            $collection = $this->catalog->createPostCollection($storeId)->withCategoryIds();
            switch ($this->currentPage->getType()) {
                case CurrentPage::TYPE_CATEGORY:
                    $collection->addCategoryFilter(
                        $this->catalog->getCategoryIdsWithChildren($this->currentPage->getCategory(), $storeId)
                    );
                    break;
                case CurrentPage::TYPE_TAG:
                    $collection->addTagFilter((int)$this->currentPage->getTag()->getId());
                    break;
                case CurrentPage::TYPE_AUTHOR:
                    $collection->addAuthorFilter((int)$this->currentPage->getAuthor()->getId());
                    break;
                default:
                    // featured posts are shown above the list on page 1 and left out of the list on every page,
                    // so that the pages do not shift (no post shown twice or skipped)
                    $collection->excludeIds(array_map(static fn (Post $post) => (int)$post->getId(), $this->loadFeaturedPosts()));
            }
            $collection->setPageSize($this->config->getPostsPerPage($storeId));
            $collection->setCurPage($this->currentPage->getPageNumber());
            $this->collection = $collection;
        }
        return $this->collection;
    }

    /**
     * @return Post[] posts of the current page
     */
    public function getPosts(): array
    {
        $collection = $this->getCollection();
        if ($this->currentPage->getPageNumber() > max(1, $collection->getLastPageNumber())) {
            return [];
        }
        return array_values($collection->getItems());
    }

    /**
     * @return Post[] featured posts (blog home page, first page)
     */
    public function getFeaturedPosts(): array
    {
        return $this->currentPage->getPageNumber() === 1 ? $this->loadFeaturedPosts() : [];
    }

    /**
     * @return Post[] featured posts of the blog home page (any page number)
     */
    private function loadFeaturedPosts(): array
    {
        if ($this->featured === null) {
            $storeId = $this->getStoreId();
            $this->featured = [];
            if ($this->currentPage->getType() === CurrentPage::TYPE_HOME
                && $this->config->getFlag('list/show_featured', $storeId)
            ) {
                $this->featured = $this->catalog->getRecentPosts(
                    $storeId,
                    $this->config->getInt('list/featured_count', 1, 12, 3, $storeId),
                    [],
                    [],
                    true
                );
            }
        }
        return $this->featured;
    }

    public function getPagerHtml(): string
    {
        $pager = $this->getChildBlock('pager');
        if (!$pager instanceof Pager) {
            return '';
        }
        $pager->setAvailableLimit([$this->config->getPostsPerPage($this->getStoreId()) => $this->config->getPostsPerPage($this->getStoreId())])
            ->setShowPerPage(false)
            ->setShowAmounts(false)
            ->setFrameLength(5)
            ->setJump(0)
            ->setCollection($this->getCollection());
        return $pager->toHtml();
    }

    public function getPageType(): ?string
    {
        return $this->currentPage->getType();
    }

    public function getCurrentCategory(): ?Category
    {
        return $this->currentPage->getCategory();
    }

    public function getCurrentTag(): ?Tag
    {
        return $this->currentPage->getTag();
    }

    public function getCurrentAuthor(): ?Author
    {
        return $this->currentPage->getAuthor();
    }

    /**
     * Category, tag or author description shown above the list (first page only).
     */
    public function getIntroHtml(): string
    {
        if ($this->currentPage->getPageNumber() > 1) {
            return '';
        }
        $html = match ($this->currentPage->getType()) {
            CurrentPage::TYPE_CATEGORY => (string)$this->currentPage->getCategory()->getData('description'),
            CurrentPage::TYPE_TAG => (string)$this->currentPage->getTag()->getData('description'),
            default => '',
        };
        return $html !== '' ? $this->contentRenderer->renderHtml($html, $this->getStoreId()) : '';
    }

    public function getEmptyText(): string
    {
        return (string)__('There are no posts here yet.');
    }

    public function getIdentities()
    {
        $tags = [Post::CACHE_TAG, Category::CACHE_TAG];
        if ($this->currentPage->getTag()) {
            $tags[] = Tag::CACHE_TAG . '_' . $this->currentPage->getTag()->getId();
        }
        if ($this->currentPage->getAuthor()) {
            $tags[] = Author::CACHE_TAG . '_' . $this->currentPage->getAuthor()->getId();
        }
        return $tags;
    }
}
