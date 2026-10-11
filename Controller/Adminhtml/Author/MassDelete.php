<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Adminhtml\Author;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_Blog::authors';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly \CommerceGears\Blog\Model\ResourceModel\Author\CollectionFactory $collectionFactory,
        private readonly \CommerceGears\Blog\Model\ResourceModel\Author $resource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $count = 0;
        foreach ($this->filter->getCollection($this->collectionFactory->create()) as $entity) {
            $this->resource->delete($entity);
            $count++;
        }
        $this->messageManager->addSuccessMessage(__('A total of %1 author(s) have been deleted.', $count));
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
