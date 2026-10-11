<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Category;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use CommerceGears\Blog\Controller\AbstractView;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\CurrentPage;
use CommerceGears\Blog\Model\Frontend\Catalog;
use CommerceGears\Blog\Model\Frontend\PageHead;

/**
 * Posts of one category.
 */
class View extends AbstractView
{
    public function __construct(
        RequestInterface $request,
        PageFactory $pageFactory,
        StoreManagerInterface $storeManager,
        Config $config,
        CurrentPage $currentPage,
        PageHead $pageHead,
        private readonly Catalog $catalog
    ) {
        parent::__construct($request, $pageFactory, $storeManager, $config, $currentPage, $pageHead);
    }

    public function execute(): ResultInterface
    {
        $this->assertEnabled();
        $entity = $this->catalog->getCategory((int)$this->request->getParam('id'), $this->getStoreId());
        if (!$entity) {
            throw new NotFoundException(__('Page not found.'));
        }
        $this->currentPage->setCategory($entity, $this->getPageNumber());
        return $this->createPage();
    }
}
