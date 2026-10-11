<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Author;

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
 * Posts of one author.
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
        $entity = $this->catalog->getAuthor((int)$this->request->getParam('id'));
        if (!$entity) {
            throw new NotFoundException(__('Page not found.'));
        }
        $this->currentPage->setAuthor($entity, $this->getPageNumber());
        return $this->createPage();
    }
}
