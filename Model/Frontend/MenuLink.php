<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Frontend;

use Magento\Framework\Data\Tree\Node;
use Magento\Store\Model\StoreManagerInterface;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\CurrentPage;
use CommerceGears\Blog\Model\Url;

/**
 * "Blog" link at the end of the main menu (Luma top menu and Hyvä navigation), when switched on in the settings.
 */
class MenuLink
{
    public function __construct(
        private readonly Config $config,
        private readonly Url $url,
        private readonly StoreManagerInterface $storeManager,
        private readonly CurrentPage $currentPage
    ) {
    }

    /**
     * @param bool $markActive false where the menu HTML is cached for every page (Hyvä)
     */
    public function addTo(Node $menu, bool $markActive = true): void
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId) || !$this->config->getFlag('general/top_menu_link', $storeId)) {
            return;
        }
        $node = new Node(
            [
                'name' => $this->config->getTitle($storeId),
                'id' => 'commercegears-blog',
                'url' => $this->url->getHomeUrl($storeId),
                'has_active' => false,
                'is_active' => $markActive && $this->currentPage->isBlogPage(),
                'is_category' => false,
                'path' => '',
            ],
            'id',
            $menu->getTree(),
            $menu
        );
        $menu->addChild($node);
    }
}
