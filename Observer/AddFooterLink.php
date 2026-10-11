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
use Softaware\Blog\Block\FooterLink;

/**
 * Adds the blog link to the footer link list of Luma-based themes ("footer_links"). Themes without that list
 * (Hyvä) are left alone, so no broken layout reference is logged.
 */
class AddFooterLink implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $layout = $observer->getData('layout');
        if (!$layout instanceof LayoutInterface || !$layout->getBlock('footer_links')
            || $layout->getBlock('softaware.blog.footer.link')
        ) {
            return;
        }
        $layout->addBlock(FooterLink::class, 'softaware.blog.footer.link', 'footer_links');
    }
}
