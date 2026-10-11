<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Adminhtml\Post;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;
use Softaware\Blog\Model\Post;
use Softaware\Blog\Model\ResourceModel\Post as PostResource;
use Softaware\Blog\Model\ResourceModel\Post\CollectionFactory;

/**
 * Publish (status=1) or unpublish (status=0) the selected posts.
 */
class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Blog::posts_publish';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly PostResource $postResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $status = (int)$this->getRequest()->getParam('status') === Post::STATUS_PUBLISHED ? Post::STATUS_PUBLISHED : Post::STATUS_DRAFT;
        $count = 0;
        foreach ($this->filter->getCollection($this->collectionFactory->create()) as $post) {
            /** @var Post $post */
            if ($post->getStatus() === $status) {
                continue;
            }
            $post->setStatus($status);
            // product pages that list the post as a related post are refreshed in the full-page cache too
            $post->setData('_affected_product_ids', $this->postResource->getProductIds((int)$post->getId()));
            $this->postResource->save($post);
            $count++;
        }
        $this->messageManager->addSuccessMessage($status === Post::STATUS_PUBLISHED
            ? __('A total of %1 post(s) have been published.', $count)
            : __('A total of %1 post(s) have been set to draft.', $count));
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
