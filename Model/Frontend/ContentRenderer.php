<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Frontend;

use Magento\Cms\Model\Template\FilterProvider;
use Psr\Log\LoggerInterface;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\Post;
use Softaware\Blog\Model\TextAnalyser;

/**
 * Post content as HTML (CMS directives and widgets, Page Builder output), excerpts and reading time.
 */
class ContentRenderer
{
    /** @var array<string, string> */
    private array $rendered = [];

    public function __construct(
        private readonly FilterProvider $filterProvider,
        private readonly Config $config,
        private readonly TextAnalyser $textAnalyser,
        private readonly LoggerInterface $logger
    ) {
    }

    public function renderHtml(string $html, int $storeId): string
    {
        if ($html === '') {
            return '';
        }
        $key = $storeId . ':' . sha1($html);
        if (!isset($this->rendered[$key])) {
            try {
                $this->rendered[$key] = (string)$this->filterProvider->getPageFilter()->setStoreId($storeId)->filter($html);
            } catch (\Throwable $e) {
                $this->logger->error('Softaware_Blog content filter: ' . $e->getMessage());
                $this->rendered[$key] = $html;
            }
        }
        return $this->rendered[$key];
    }

    public function getContentHtml(Post $post, int $storeId): string
    {
        return $this->renderHtml($post->getContent(), $storeId);
    }

    /**
     * Excerpt HTML: the post's own excerpt (CMS directives allowed), else the start of the content as plain text.
     */
    public function getExcerptHtml(Post $post, int $storeId): string
    {
        $excerpt = trim($post->getShortContent());
        if ($excerpt !== '') {
            return $this->renderHtml($excerpt, $storeId);
        }
        $text = $this->textAnalyser->toPlainText($this->getContentHtml($post, $storeId));
        $limited = $this->textAnalyser->truncate($text, $this->config->getExcerptLength($storeId));
        return htmlspecialchars($limited, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Plain text summary for meta descriptions, feeds and structured data.
     */
    public function getSummary(Post $post, int $storeId, int $length = 160): string
    {
        $source = trim($post->getShortContent()) !== '' ? $post->getShortContent() : $post->getContent();
        $text = $this->textAnalyser->toPlainText($this->renderHtml($source, $storeId));
        return $this->textAnalyser->truncate($text, $length);
    }

    public function getReadingMinutes(Post $post, int $storeId): int
    {
        return $this->textAnalyser->readingMinutes(
            $this->textAnalyser->wordCount($this->textAnalyser->toPlainText($this->getContentHtml($post, $storeId))),
            $this->config->getWordsPerMinute($storeId)
        );
    }
}
