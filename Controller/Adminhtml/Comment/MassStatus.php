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
use CommerceGears\Blog\Model\Comment;
use CommerceGears\Blog\Model\ResourceModel\Comment as CommentResource;
use CommerceGears\Blog\Model\ResourceModel\Comment\CollectionFactory;

/**
 * Approve, reject or set the selected comments back to pending.
 */
class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_Blog::comments_moderate';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly CommentResource $commentResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $status = (int)$this->getRequest()->getParam('status');
        if (!in_array($status, [Comment::STATUS_PENDING, Comment::STATUS_APPROVED, Comment::STATUS_REJECTED], true)) {
            $this->messageManager->addErrorMessage(__('Unknown comment status.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $count = 0;
        foreach ($this->filter->getCollection($this->collectionFactory->create()) as $comment) {
            if ((int)$comment->getData('status') === $status) {
                continue;
            }
            $comment->setData('status', $status);
            $this->commentResource->save($comment);
            $count++;
        }
        $messages = [
            Comment::STATUS_APPROVED => __('A total of %1 comment(s) have been approved.', $count),
            Comment::STATUS_REJECTED => __('A total of %1 comment(s) have been rejected.', $count),
            Comment::STATUS_PENDING => __('A total of %1 comment(s) have been set to pending.', $count),
        ];
        $this->messageManager->addSuccessMessage($messages[$status]);
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
