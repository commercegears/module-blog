<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\CurrentPage;

/**
 * On blog pages the module writes its own BreadcrumbList, so Hyvä's breadcrumb JSON-LD is switched off
 * (same flag Softaware_Seo uses on catalog pages).
 */
class SkipThemeBreadcrumbSchema implements ObserverInterface
{
    public function __construct(
        private readonly CurrentPage $currentPage,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->currentPage->isBlogPage()
            || !$this->config->getFlag('seo/json_ld', (int)$this->storeManager->getStore()->getId())
        ) {
            return;
        }
        /** @var LayoutInterface $layout */
        $layout = $observer->getData('layout');
        $block = $layout ? $layout->getBlock('breadcrumbs') : false;
        if ($block) {
            $block->setData('skip_ld_json_schema', true);
        }
    }
}
