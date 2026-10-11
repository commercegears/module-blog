<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Tag;

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

/**
 * Posts of one tag.
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
        $entity = $this->catalog->getTags([(int)$this->request->getParam('id')], $this->getStoreId());
        $entity = reset($entity);
        if (!$entity) {
            throw new NotFoundException(__('Page not found.'));
        }
        $this->currentPage->setTag($entity, $this->getPageNumber());
        return $this->createPage();
    }
}
