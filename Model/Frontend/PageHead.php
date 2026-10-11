<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Frontend;

use Magento\Framework\View\Result\Page;
use Magento\Store\Model\StoreManagerInterface;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\CurrentPage;
use Softaware\Blog\Model\Source\Robots;

/**
 * Title, meta tags, robots, canonical, RSS link, page heading and breadcrumbs of a blog page.
 */
class PageHead
{
    public function __construct(
        private readonly CurrentPage $currentPage,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly PageMeta $pageMeta
    ) {
    }

    public function apply(Page $page): void
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $meta = $this->pageMeta->get();
        $pageConfig = $page->getConfig();

        $title = $meta['title'] . $this->config->getValue('seo/title_suffix', $storeId);
        $pageConfig->getTitle()->set($title);
        $pageConfig->setMetaTitle($title);
        if ($meta['description'] !== '') {
            $pageConfig->setDescription($meta['description']);
        }
        if ($meta['keywords'] !== '') {
            $pageConfig->setKeywords($meta['keywords']);
        }
        $robots = $this->currentPage->isPreview() ? 'NOINDEX,NOFOLLOW' : $meta['robots'];
        if (in_array($robots, Robots::VALUES, true)) {
            $pageConfig->setRobots($robots);
        }
        if ($meta['canonical'] !== '') {
            $pageConfig->addRemotePageAsset($meta['canonical'], 'canonical', ['attributes' => ['rel' => 'canonical']]);
        }
        if ($this->config->getFlag('rss/enabled', $storeId)) {
            $pageConfig->addRemotePageAsset(
                $meta['rss'],
                'unknown',
                ['attributes' => ['rel' => 'alternate', 'type' => 'application/rss+xml', 'title' => $this->config->getTitle($storeId)]]
            );
        }
        $pageConfig->addBodyClass('softaware-blog')->addBodyClass('softaware-blog-' . $this->currentPage->getType());

        $layout = $page->getLayout();
        $heading = $layout->getBlock('page.main.title');
        if ($heading) {
            $heading->setData('page_title', $meta['heading']);
        }
        $breadcrumbs = $layout->getBlock('breadcrumbs');
        if ($breadcrumbs) {
            foreach ($meta['breadcrumbs'] as $index => $crumb) {
                $breadcrumbs->addCrumb('blog_' . $index, [
                    'label' => $crumb['label'],
                    'title' => $crumb['label'],
                    'link' => $crumb['url'] ?? null,
                ]);
            }
        }
    }
}
