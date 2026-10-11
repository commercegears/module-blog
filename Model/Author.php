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
use CommerceGears\Blog\Model\ResourceModel\Author as AuthorResource;

class Author extends AbstractModel implements IdentityInterface
{
    public const CACHE_TAG = 'swb_a';

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'commercegears_blog_author';

    protected function _construct()
    {
        $this->_init(AuthorResource::class);
    }

    public function getIdentities()
    {
        // Author name and photo are shown on post pages, so posts are refreshed as well.
        return $this->getId()
            ? [self::CACHE_TAG, self::CACHE_TAG . '_' . $this->getId(), Post::CACHE_TAG]
            : [self::CACHE_TAG];
    }

    public function getName(): string
    {
        return (string)$this->getData('name');
    }

    public function getUrlKey(): string
    {
        return (string)$this->getData('url_key');
    }

    public function isActive(): bool
    {
        return (bool)$this->getData('is_active');
    }
}
