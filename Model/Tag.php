<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model;

use Softaware\Blog\Model\ResourceModel\Tag as TagResource;

class Tag extends AbstractContentModel
{
    public const CACHE_TAG = 'swb_t';
    public const STORE_FIELDS = ['name', 'url_key', 'description', 'meta_title', 'meta_description', 'meta_robots'];

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'softaware_blog_tag';

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
