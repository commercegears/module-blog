<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\CurrentPage;
use CommerceGears\Blog\Model\Frontend\PageHead;

/**
 * Base for the storefront blog pages.
 */
abstract class AbstractView implements HttpGetActionInterface
{
    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly PageFactory $pageFactory,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly Config $config,
        protected readonly CurrentPage $currentPage,
        protected readonly PageHead $pageHead
    ) {
    }

    protected function getStoreId(): int
    {
        return (int)$this->storeManager->getStore()->getId();
    }

    protected function getPageNumber(): int
    {
        return max(1, (int)$this->request->getParam('p', 1));
    }

    /**
     * @throws NotFoundException
     */
    protected function assertEnabled(): void
    {
        if (!$this->config->isEnabled($this->getStoreId())) {
            throw new NotFoundException(__('Page not found.'));
        }
    }

    protected function createPage(): Page
    {
        $page = $this->pageFactory->create();
        $page->addHandle('commercegears_blog');
        if ($this->currentPage->isPreview()) {
            // holds an uncacheable block, so a draft never reaches the full-page cache
            $page->addHandle('commercegears_blog_preview');
        }
        $this->pageHead->apply($page);
        return $page;
    }
}
