<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Adminhtml\Comment;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_Blog::comments_delete';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly \CommerceGears\Blog\Model\ResourceModel\Comment\CollectionFactory $collectionFactory,
        private readonly \CommerceGears\Blog\Model\ResourceModel\Comment $resource
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
        $this->messageManager->addSuccessMessage(__('A total of %1 comment(s) have been deleted.', $count));
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
