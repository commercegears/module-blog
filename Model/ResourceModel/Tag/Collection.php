<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\ResourceModel\Tag;

use Softaware\Blog\Model\ResourceModel\AbstractContentCollection;
use Softaware\Blog\Model\ResourceModel\Tag as TagResource;
use Softaware\Blog\Model\Tag;

class Collection extends AbstractContentCollection
{
    protected $_idFieldName = 'tag_id';

    protected function _construct()
    {
        $this->_init(Tag::class, TagResource::class);
    }
}
