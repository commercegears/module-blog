<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use CommerceGears\Blog\Api\Data\PostInterface;
use CommerceGears\Blog\Api\PostRepositoryInterface;
use CommerceGears\Blog\Model\ResourceModel\Post as PostResource;

class PostRepository implements PostRepositoryInterface
{
    public function __construct(
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource
    ) {
    }

    public function getById(int $postId, int $storeId = 0): PostInterface
    {
        $post = $this->postFactory->create();
        $post->setStoreId($storeId);
        $this->postResource->load($post, $postId);
        if (!$post->getId()) {
            throw new NoSuchEntityException(__('The blog post with ID "%1" does not exist.', $postId));
        }
        return $post;
    }

    public function save(PostInterface $post): PostInterface
    {
        try {
            /** @var Post $post */
            $this->postResource->save($post);
        } catch (LocalizedException $e) {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('The blog post could not be saved: %1', $e->getMessage()), $e);
        }
        return $post;
    }

    public function delete(PostInterface $post): void
    {
        try {
            /** @var Post $post */
            $this->postResource->delete($post);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('The blog post could not be deleted: %1', $e->getMessage()), $e);
        }
    }

    public function deleteById(int $postId): void
    {
        $this->delete($this->getById($postId));
    }
}
