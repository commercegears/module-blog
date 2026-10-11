<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Frontend;

use Magento\Store\Model\StoreManagerInterface;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\CurrentPage;
use Softaware\Blog\Model\Category;
use Softaware\Blog\Model\TextAnalyser;
use Softaware\Blog\Model\Url;

/**
 * Meta data of the current blog page, shared by the head, Open Graph and structured data.
 *
 * @phpstan-type Meta array{title: string, heading: string, description: string, keywords: string, robots: string,
 *     canonical: string, rss: string, image: string, image_alt: string,
 *     breadcrumbs: array<int, array{label: string, url?: string}>}
 */
class PageMeta
{
    private ?array $meta = null;

    public function __construct(
        private readonly CurrentPage $currentPage,
        private readonly Config $config,
        private readonly Url $url,
        private readonly Catalog $catalog,
        private readonly ContentRenderer $contentRenderer,
        private readonly StoreManagerInterface $storeManager,
        private readonly TextAnalyser $textAnalyser
    ) {
    }

    /**
     * @return Meta
     */
    public function get(): array
    {
        if ($this->meta === null) {
            $this->meta = $this->build();
        }
        return $this->meta;
    }

    private function build(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $blogTitle = $this->config->getTitle($storeId);
        $homeUrl = $this->url->getHomeUrl($storeId);
        $page = $this->currentPage->getPageNumber();
        $pageSuffix = $page > 1 ? ' (' . __('Page %1', $page) . ')' : '';
        $crumbs = [
            ['label' => (string)__('Home'), 'url' => $this->storeManager->getStore()->getBaseUrl()],
        ];
        $meta = [
            'title' => $blogTitle,
            'heading' => $blogTitle,
            'description' => '',
            'keywords' => '',
            'robots' => '',
            'canonical' => '',
            'rss' => $this->url->getRssUrl($storeId),
            'image' => '',
            'image_alt' => '',
        ];

        switch ($this->currentPage->getType()) {
            case CurrentPage::TYPE_HOME:
                $meta['title'] = ($this->config->getValue('seo/home_meta_title', $storeId) ?: $blogTitle) . $pageSuffix;
                $meta['description'] = $this->config->getValue('seo/home_meta_description', $storeId);
                $meta['keywords'] = $this->config->getValue('seo/home_meta_keywords', $storeId);
                $meta['canonical'] = $this->paged($homeUrl, $page);
                $crumbs[] = ['label' => $blogTitle];
                break;

            case CurrentPage::TYPE_POST:
                $post = $this->currentPage->getPost();
                $meta['title'] = (string)($post->getData('meta_title') ?: $post->getTitle());
                $meta['heading'] = $post->getTitle();
                $meta['description'] = (string)($post->getData('meta_description') ?: $this->contentRenderer->getSummary($post, $storeId));
                $meta['keywords'] = (string)$post->getData('meta_keywords');
                $meta['robots'] = (string)($post->getData('meta_robots') ?: $this->config->getRobots('post', $storeId));
                $meta['canonical'] = (string)($post->getData('canonical_url') ?: $this->url->getPostUrl($post, $storeId));
                $meta['image'] = $this->url->getMediaUrl($post->getFeaturedImage(), $storeId);
                $meta['image_alt'] = (string)($post->getData('featured_image_alt') ?: $post->getTitle());
                $crumbs[] = ['label' => $blogTitle, 'url' => $homeUrl];
                $category = $this->getMainCategory($post->getCategoryIds(), $storeId);
                if ($category) {
                    foreach ($this->catalog->getCategoryPath($category, $storeId) as $item) {
                        $crumbs[] = ['label' => $item->getName(), 'url' => $this->url->getCategoryUrl($item, $storeId)];
                    }
                }
                $crumbs[] = ['label' => $post->getTitle()];
                break;

            case CurrentPage::TYPE_CATEGORY:
                $category = $this->currentPage->getCategory();
                $meta['title'] = (string)($category->getData('meta_title') ?: $category->getName()) . $pageSuffix;
                $meta['heading'] = $category->getName();
                $meta['description'] = (string)($category->getData('meta_description')
                    ?: $this->plain((string)$category->getData('description'), $storeId));
                $meta['keywords'] = (string)$category->getData('meta_keywords');
                $meta['robots'] = (string)($category->getData('meta_robots') ?: $this->config->getRobots('category', $storeId));
                $meta['canonical'] = $this->paged($this->url->getCategoryUrl($category, $storeId), $page);
                $meta['rss'] = $this->url->getRssUrl($storeId, $category->getUrlKey());
                $crumbs[] = ['label' => $blogTitle, 'url' => $homeUrl];
                $path = $this->catalog->getCategoryPath($category, $storeId);
                array_pop($path);
                foreach ($path as $item) {
                    $crumbs[] = ['label' => $item->getName(), 'url' => $this->url->getCategoryUrl($item, $storeId)];
                }
                $crumbs[] = ['label' => $category->getName()];
                break;

            case CurrentPage::TYPE_TAG:
                $tag = $this->currentPage->getTag();
                $meta['title'] = (string)($tag->getData('meta_title') ?: __('Posts tagged "%1"', $tag->getName())) . $pageSuffix;
                $meta['heading'] = (string)__('Posts tagged "%1"', $tag->getName());
                $meta['description'] = (string)($tag->getData('meta_description')
                    ?: $this->plain((string)$tag->getData('description'), $storeId));
                $meta['robots'] = (string)($tag->getData('meta_robots') ?: $this->config->getRobots('tag', $storeId));
                $meta['canonical'] = $this->paged($this->url->getTagUrl($tag, $storeId), $page);
                $crumbs[] = ['label' => $blogTitle, 'url' => $homeUrl];
                $crumbs[] = ['label' => $tag->getName()];
                break;

            case CurrentPage::TYPE_AUTHOR:
                $author = $this->currentPage->getAuthor();
                $meta['title'] = (string)($author->getData('meta_title') ?: __('Posts by %1', $author->getName())) . $pageSuffix;
                $meta['heading'] = $author->getName();
                $meta['description'] = (string)($author->getData('meta_description')
                    ?: $this->plain((string)$author->getData('bio'), $storeId));
                $meta['robots'] = $this->config->getRobots('author', $storeId);
                $meta['canonical'] = $this->paged($this->url->getAuthorUrl($author, $storeId), $page);
                $meta['image'] = $this->url->getMediaUrl((string)$author->getData('photo'), $storeId);
                $meta['image_alt'] = $author->getName();
                $crumbs[] = ['label' => $blogTitle, 'url' => $homeUrl];
                $crumbs[] = ['label' => $author->getName()];
                break;
        }
        if ($page > 1 && $this->currentPage->getType() !== CurrentPage::TYPE_POST) {
            $paginated = $this->config->getRobots('paginated', $storeId);
            if (str_starts_with($paginated, 'NOINDEX')) {
                $meta['robots'] = $paginated;
            }
        }
        $meta['breadcrumbs'] = $crumbs;
        return $meta;
    }

    /**
     * @param int[] $categoryIds
     */
    public function getMainCategory(array $categoryIds, int $storeId): ?Category
    {
        $best = null;
        foreach ($categoryIds as $categoryId) {
            $category = $this->catalog->getCategory((int)$categoryId, $storeId);
            if ($category && ($best === null || (int)$category->getData('level') > (int)$best->getData('level'))) {
                $best = $category;
            }
        }
        return $best;
    }

    private function paged(string $url, int $page): string
    {
        return $page > 1 ? $url . '?p=' . $page : $url;
    }

    private function plain(string $html, int $storeId): string
    {
        if ($html === '') {
            return '';
        }
        return $this->textAnalyser->truncate($this->textAnalyser->toPlainText($this->contentRenderer->renderHtml($html, $storeId)), 160);
    }
}
