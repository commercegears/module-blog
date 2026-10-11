<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Softaware\Blog\Model\Author;
use Softaware\Blog\Model\Category;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\CurrentPage;
use Softaware\Blog\Model\Frontend\BlockContext;
use Softaware\Blog\Model\Frontend\Catalog;
use Softaware\Blog\Model\Frontend\ContentRenderer;
use Softaware\Blog\Model\Post;
use Softaware\Blog\Model\Tag;
use Softaware\Blog\Model\Url;

/**
 * Shared helpers for storefront blog blocks and templates.
 */
abstract class AbstractBlog extends Template
{
    protected Config $config;
    protected Catalog $catalog;
    protected Url $blogUrl;
    protected ContentRenderer $contentRenderer;
    protected CurrentPage $currentPage;
    protected BlockContext $blogContext;

    public function __construct(Context $context, BlockContext $blogContext, array $data = [])
    {
        $this->blogContext = $blogContext;
        $this->config = $blogContext->config;
        $this->catalog = $blogContext->catalog;
        $this->blogUrl = $blogContext->url;
        $this->contentRenderer = $blogContext->contentRenderer;
        $this->currentPage = $blogContext->currentPage;
        parent::__construct($context, $data);
    }

    public function getStoreId(): int
    {
        return (int)$this->_storeManager->getStore()->getId();
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled($this->getStoreId());
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getBlogTitle(): string
    {
        return $this->config->getTitle($this->getStoreId());
    }

    public function getBlogUrl(): string
    {
        return $this->blogUrl->getHomeUrl($this->getStoreId());
    }

    public function getPostUrl(Post $post): string
    {
        return $this->blogUrl->getPostUrl($post, $this->getStoreId());
    }

    public function getCategoryUrl(Category $category): string
    {
        return $this->blogUrl->getCategoryUrl($category, $this->getStoreId());
    }

    public function getTagUrl(Tag $tag): string
    {
        return $this->blogUrl->getTagUrl($tag, $this->getStoreId());
    }

    public function getAuthorUrl(Author $author): string
    {
        return $this->blogUrl->getAuthorUrl($author, $this->getStoreId());
    }

    public function getImageUrl(string $file): string
    {
        return $this->blogUrl->getMediaUrl($file, $this->getStoreId());
    }

    public function getExcerptHtml(Post $post): string
    {
        return $this->contentRenderer->getExcerptHtml($post, $this->getStoreId());
    }

    public function getAuthor(Post $post): ?Author
    {
        return $post->getAuthorId() ? $this->catalog->getAuthor($post->getAuthorId()) : null;
    }

    /**
     * @return Category[]
     */
    public function getPostCategories(Post $post): array
    {
        $result = [];
        foreach ($post->getCategoryIds() as $categoryId) {
            $category = $this->catalog->getCategory((int)$categoryId, $this->getStoreId());
            if ($category) {
                $result[] = $category;
            }
        }
        return $result;
    }

    public function getReadingMinutes(Post $post): int
    {
        return $this->config->getFlag('post/show_reading_time', $this->getStoreId())
            ? $this->contentRenderer->getReadingMinutes($post, $this->getStoreId())
            : 0;
    }

    public function formatPublishDate(Post $post, int $format = \IntlDateFormatter::MEDIUM): string
    {
        $date = $post->getPublishAt() ?: (string)$post->getData('created_at');
        if ($date === '') {
            return '';
        }
        try {
            return (string)$this->_localeDate->formatDateTime(
                new \DateTime($date, new \DateTimeZone('UTC')),
                $format,
                \IntlDateFormatter::NONE
            );
        } catch (\Exception) {
            return '';
        }
    }

    public function getIsoDate(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        try {
            return (new \DateTime($utc, new \DateTimeZone('UTC')))->format(DATE_ATOM);
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * Post card used by lists, related posts and widgets (theme-specific template).
     *
     * @param string $variant default, compact or featured
     */
    public function renderCard(Post $post, string $variant = 'default', string $headingTag = 'h2'): string
    {
        $this->setData('card_post', $post);
        $this->setData('card_variant', $variant);
        $this->setData('card_heading', in_array($headingTag, ['h2', 'h3', 'h4'], true) ? $headingTag : 'h2');
        $template = $this->isHyva() ? 'Softaware_Blog::hyva/post/card.phtml' : 'Softaware_Blog::post/card.phtml';
        $html = $this->fetchView($this->getTemplateFile($template));
        $this->unsetData('card_post');
        return $html;
    }

    public function isHyva(): bool
    {
        return $this->blogContext->themeResolver->isHyva();
    }
}
