<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Api;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use CommerceGears\Blog\Api\Data\PostInterface;

interface PostRepositoryInterface
{
    /**
     * @param int $postId
     * @param int $storeId 0 = default values
     * @throws NoSuchEntityException
     */
    public function getById(int $postId, int $storeId = 0): PostInterface;

    /**
     * Saves the default values (store view values are kept).
     *
     * @throws CouldNotSaveException
     */
    public function save(PostInterface $post): PostInterface;

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(PostInterface $post): void;

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $postId): void;
}
