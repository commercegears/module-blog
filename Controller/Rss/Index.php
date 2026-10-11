<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Rss;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Exception\NotFoundException;
use Magento\Store\Model\StoreManagerInterface;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\Rss\FeedBuilder;

/**
 * RSS 2.0 feed of the latest posts (optionally of one category: ?category=url-key).
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly FeedBuilder $feedBuilder
    ) {
    }

    public function execute(): Raw
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId) || !$this->config->getFlag('rss/enabled', $storeId)) {
            throw new NotFoundException(__('Page not found.'));
        }
        $xml = $this->feedBuilder->build($storeId, (string)$this->request->getParam('category'));
        if ($xml === null) {
            throw new NotFoundException(__('Page not found.'));
        }
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'application/rss+xml; charset=UTF-8', true);
        $result->setHeader('Cache-Control', 'public, max-age=900', true);
        return $result->setContents($xml);
    }
}
