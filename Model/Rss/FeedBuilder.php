<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Rss;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\Frontend\Catalog;
use CommerceGears\Blog\Model\Frontend\ContentRenderer;
use CommerceGears\Blog\Model\ResourceModel\Category as CategoryResource;
use CommerceGears\Blog\Model\Url;

/**
 * Builds the RSS 2.0 document.
 */
class FeedBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly Catalog $catalog,
        private readonly CategoryResource $categoryResource,
        private readonly ContentRenderer $contentRenderer,
        private readonly Url $url,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return string|null NULL when the category does not exist
     */
    public function build(int $storeId, string $categoryKey = ''): ?string
    {
        $title = $this->config->getTitle($storeId);
        $link = $this->url->getHomeUrl($storeId);
        $collection = $this->catalog->createPostCollection($storeId)->withCategoryIds();
        if ($categoryKey !== '') {
            $category = $this->catalog->getCategory($this->categoryResource->findIdByUrlKey($categoryKey, $storeId), $storeId);
            if (!$category) {
                return null;
            }
            $collection->addCategoryFilter($this->catalog->getCategoryIdsWithChildren($category, $storeId));
            $title .= ': ' . $category->getName();
            $link = $this->url->getCategoryUrl($category, $storeId);
        }
        $collection->setPageSize($this->config->getInt('rss/items', 1, 100, 20, $storeId))->setCurPage(1);
        $fullContent = $this->config->getFlag('rss/full_content', $storeId);

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $xml->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $xml->startElement('channel');
        $xml->writeElement('title', $title);
        $xml->writeElement('link', $link);
        $xml->writeElement('description', $this->config->getValue('seo/home_meta_description', $storeId) ?: $title);
        $xml->writeElement('language', strtolower(str_replace('_', '-', (string)$this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ))));
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $this->url->getRssUrl($storeId, $categoryKey));
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();
        $first = true;
        foreach ($collection as $post) {
            if ($first) {
                $xml->writeElement('lastBuildDate', $this->rfc822((string)$post->getPublishAt()));
                $first = false;
            }
            $url = $this->url->getPostUrl($post, $storeId);
            $xml->startElement('item');
            $xml->writeElement('title', $post->getTitle());
            $xml->writeElement('link', $url);
            $xml->startElement('guid');
            $xml->writeAttribute('isPermaLink', 'true');
            $xml->text($url);
            $xml->endElement();
            $xml->writeElement('pubDate', $this->rfc822((string)$post->getPublishAt()));
            $author = $post->getAuthorId() ? $this->catalog->getAuthor($post->getAuthorId()) : null;
            if ($author) {
                $xml->writeElement('dc:creator', $author->getName());
            }
            foreach ($post->getCategoryIds() as $categoryId) {
                $category = $this->catalog->getCategory((int)$categoryId, $storeId);
                if ($category) {
                    $xml->writeElement('category', $category->getName());
                }
            }
            $xml->writeElement('description', $this->contentRenderer->getSummary($post, $storeId, 400));
            if ($fullContent) {
                $xml->startElement('content:encoded');
                $xml->writeCdata(str_replace(']]>', ']]&gt;', $this->contentRenderer->getContentHtml($post, $storeId)));
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();
        return $xml->outputMemory();
    }

    private function rfc822(string $utc): string
    {
        try {
            return (new \DateTime($utc ?: 'now', new \DateTimeZone('UTC')))->format(DATE_RSS);
        } catch (\Exception) {
            return gmdate(DATE_RSS);
        }
    }
}
