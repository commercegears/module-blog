<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block;

use Magento\Framework\View\Element\Html\Link;
use Magento\Framework\View\Element\Template\Context;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\Url;

/**
 * Blog link in the Luma footer links (Stores > Configuration > CommerceGears > Blog > General).
 */
class FooterLink extends Link
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly Url $url,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getHref()
    {
        return $this->url->getHomeUrl();
    }

    public function getLabel()
    {
        return $this->config->getTitle();
    }

    protected function _toHtml()
    {
        $storeId = (int)$this->_storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId) || !$this->config->getFlag('general/footer_link', $storeId)) {
            return '';
        }
        return parent::_toHtml();
    }
}
