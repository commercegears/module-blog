<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use CommerceGears\Blog\Model\ResourceModel\Comment as CommentResource;

class Comment extends AbstractModel implements IdentityInterface
{
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    protected $_eventPrefix = 'commercegears_blog_comment';

    protected function _construct()
    {
        $this->_init(CommentResource::class);
    }

    /**
     * Comments are shown on their post page only.
     */
    public function getIdentities()
    {
        return $this->getPostId() ? [Post::CACHE_TAG . '_' . $this->getPostId()] : [];
    }

    public function getPostId(): int
    {
        return (int)$this->getData('post_id');
    }

    public function getStatus(): int
    {
        return (int)$this->getData('status');
    }

    public function getContent(): string
    {
        return (string)$this->getData('content');
    }

    public function getAuthorName(): string
    {
        return (string)$this->getData('author_name');
    }
}
