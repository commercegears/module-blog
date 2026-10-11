<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Adminhtml\Author;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Blog::authors';

    public function __construct(
        Action\Context $context,
        private readonly \Softaware\Blog\Model\AuthorFactory $factory,
        private readonly \Softaware\Blog\Model\ResourceModel\Author $resource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        $entity = $this->factory->create();
        $this->resource->load($entity, $id);
        if (!$entity->getId()) {
            $this->messageManager->addErrorMessage(__('This record no longer exists.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        try {
            $this->resource->delete($entity);
            $this->messageManager->addSuccessMessage(__('The author has been deleted. Their posts are kept without an author.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->resultRedirectFactory->create()->setPath('*/*/edit', ['id' => $id]);
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
