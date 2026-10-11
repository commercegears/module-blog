<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use CommerceGears\Blog\Block\FooterLink;

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
            || $layout->getBlock('commercegears.blog.footer.link')
        ) {
            return;
        }
        $layout->addBlock(FooterLink::class, 'commercegears.blog.footer.link', 'footer_links');
    }
}
