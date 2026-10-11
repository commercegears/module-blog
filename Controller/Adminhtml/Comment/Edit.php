<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Adminhtml\Comment;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_Blog::comments';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $pageFactory,
        private readonly \CommerceGears\Blog\Model\CommentFactory $factory,
        private readonly \CommerceGears\Blog\Model\ResourceModel\Comment $resource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        $title = (string)__('Comment');
        if ($id) {
            $entity = $this->factory->create();
            $this->resource->load($entity, $id);
            if (!$entity->getId()) {
                $this->messageManager->addErrorMessage(__('This record no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
            $title = (string)__('Comment by %1', (string)$entity->getData('author_name'));
        }
        $page = $this->pageFactory->create();
        $page->setActiveMenu('CommerceGears_Blog::comments');
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }
}
