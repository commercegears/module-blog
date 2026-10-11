<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Post;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Softaware\Blog\Controller\AbstractView;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\CurrentPage;
use Softaware\Blog\Model\Frontend\Catalog;
use Softaware\Blog\Model\Frontend\PageHead;
use Softaware\Blog\Model\PostFactory;
use Softaware\Blog\Model\Preview;
use Softaware\Blog\Model\ResourceModel\Post as PostResource;

class View extends AbstractView
{
    public function __construct(
        RequestInterface $request,
        PageFactory $pageFactory,
        StoreManagerInterface $storeManager,
        Config $config,
        CurrentPage $currentPage,
        PageHead $pageHead,
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource,
        private readonly Catalog $catalog,
        private readonly Preview $preview
    ) {
        parent::__construct($request, $pageFactory, $storeManager, $config, $currentPage, $pageHead);
    }

    public function execute(): ResultInterface
    {
        $this->assertEnabled();
        $storeId = $this->getStoreId();
        $post = $this->postFactory->create()->setStoreId($storeId);
        $this->postResource->load($post, (int)$this->request->getParam('id'));
        if (!$post->getId()) {
            throw new NotFoundException(__('Page not found.'));
        }
        $isPreview = false;
        if (!$this->catalog->isVisible($post, $storeId)) {
            $token = (string)$this->request->getParam(Preview::PARAM);
            if ($token === '' || !$this->preview->isValid((int)$post->getId(), $token)) {
                throw new NotFoundException(__('Page not found.'));
            }
            $isPreview = true;
        }
        $this->currentPage->setPost($post, $isPreview);
        return $this->createPage();
    }
}
