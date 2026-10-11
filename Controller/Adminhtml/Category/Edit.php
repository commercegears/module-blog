<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_Blog::categories';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $pageFactory,
        private readonly \CommerceGears\Blog\Model\CategoryFactory $factory,
        private readonly \CommerceGears\Blog\Model\ResourceModel\Category $resource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        $title = (string)__('New Category');
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
        $page->setActiveMenu('CommerceGears_Blog::categories');
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }
}
