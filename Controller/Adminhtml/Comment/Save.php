<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Adminhtml\Comment;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Softaware\Blog\Model\Comment;
use Softaware\Blog\Model\CommentFactory;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\ResourceModel\Comment as CommentResource;

/**
 * Moderation: status and text of a comment, and an optional reply in the store's name.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Blog::comments_moderate';

    public function __construct(
        Action\Context $context,
        private readonly CommentFactory $commentFactory,
        private readonly CommentResource $commentResource,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $data = (array)$this->getRequest()->getPostValue();
        $redirect = $this->resultRedirectFactory->create();
        $id = (int)($data['comment_id'] ?? 0);
        try {
            $comment = $this->commentFactory->create();
            $this->commentResource->load($comment, $id);
            if (!$comment->getId()) {
                throw new LocalizedException(__('This comment no longer exists.'));
            }
            $status = (int)($data['status'] ?? Comment::STATUS_PENDING);
            if (!in_array($status, [Comment::STATUS_PENDING, Comment::STATUS_APPROVED, Comment::STATUS_REJECTED], true)) {
                $status = Comment::STATUS_PENDING;
            }
            $content = trim(str_replace("\r\n", "\n", (string)($data['content'] ?? '')));
            if ($content === '') {
                throw new LocalizedException(__('The comment text cannot be empty.'));
            }
            $comment->setData('status', $status);
            $comment->setData('content', $content);
            $this->commentResource->save($comment);

            $reply = trim(str_replace("\r\n", "\n", (string)($data['reply'] ?? '')));
            if ($reply !== '') {
                $storeId = (int)$comment->getData('store_id');
                $name = $this->config->getValue('comments/reply_name', $storeId ?: null);
                if ($name === '') {
                    $name = (string)$this->storeManager->getStore($storeId ?: null)->getFrontendName();
                }
                $answer = $this->commentFactory->create();
                $answer->setData([
                    'post_id' => $comment->getPostId(),
                    'parent_id' => (int)($comment->getData('parent_id') ?: $comment->getId()),
                    'store_id' => $storeId ?: null,
                    'author_name' => mb_substr($name, 0, 100),
                    'content' => $reply,
                    'status' => Comment::STATUS_APPROVED,
                    'is_admin_reply' => 1,
                ]);
                $this->commentResource->save($answer);
                if ($status !== Comment::STATUS_APPROVED) {
                    $this->messageManager->addNoticeMessage(__('Your reply is published once the comment it answers is approved.'));
                }
            }
            $this->messageManager->addSuccessMessage($reply !== '' ? __('The comment and your reply have been saved.') : __('The comment has been saved.'));
            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['id' => $id]);
            }
            return $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('The comment could not be saved.'));
        }
        return $redirect->setPath($id ? '*/*/edit' : '*/*/', array_filter(['id' => $id]));
    }
}
