<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Adminhtml\Author;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Blog::authors';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $pageFactory,
        private readonly \Softaware\Blog\Model\AuthorFactory $factory,
        private readonly \Softaware\Blog\Model\ResourceModel\Author $resource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        $title = (string)__('New Author');
        if ($id) {
            $entity = $this->factory->create();
            $this->resource->load($entity, $id);
            if (!$entity->getId()) {
                $this->messageManager->addErrorMessage(__('This record no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
            $title = (string)$entity->getData('name');
        }
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Softaware_Blog::authors');
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }
}
