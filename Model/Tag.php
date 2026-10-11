<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

use CommerceGears\Blog\Model\ResourceModel\Tag as TagResource;

class Tag extends AbstractContentModel
{
    public const CACHE_TAG = 'swb_t';
    public const STORE_FIELDS = ['name', 'url_key', 'description', 'meta_title', 'meta_description', 'meta_robots'];

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'commercegears_blog_tag';

    protected function _construct()
    {
        $this->_init(TagResource::class);
    }

    public function getIdentities()
    {
        return $this->getId() ? [self::CACHE_TAG, self::CACHE_TAG . '_' . $this->getId()] : [self::CACHE_TAG];
    }

    public function getName(): string
    {
        return (string)$this->getData('name');
    }

    public function getUrlKey(): string
    {
        return (string)$this->getData('url_key');
    }
}
