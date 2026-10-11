<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Observer;

use Magento\Framework\Data\Tree\Node;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Softaware\Blog\Model\Frontend\MenuLink;

/**
 * Luma top menu: adds the blog link (event page_block_html_topmenu_gethtml_before).
 */
class AddTopMenuLink implements ObserverInterface
{
    public function __construct(private readonly MenuLink $menuLink)
    {
    }

    public function execute(Observer $observer): void
    {
        $menu = $observer->getData('menu');
        if ($menu instanceof Node) {
            $this->menuLink->addTo($menu);
        }
    }
}
