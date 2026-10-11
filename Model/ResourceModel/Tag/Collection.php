<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\ResourceModel\Tag;

use CommerceGears\Blog\Model\ResourceModel\AbstractContentCollection;
use CommerceGears\Blog\Model\ResourceModel\Tag as TagResource;
use CommerceGears\Blog\Model\Tag;

class Collection extends AbstractContentCollection
{
    protected $_idFieldName = 'tag_id';

    protected function _construct()
    {
        $this->_init(Tag::class, TagResource::class);
    }
}
