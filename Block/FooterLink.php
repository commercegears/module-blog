<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block;

use Magento\Framework\View\Element\Html\Link;
use Magento\Framework\View\Element\Template\Context;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\Url;

/**
 * Blog link in the Luma footer links (Stores > Configuration > Softaware > Blog > General).
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
