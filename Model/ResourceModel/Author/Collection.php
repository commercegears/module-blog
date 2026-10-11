<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\ResourceModel\Author;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use CommerceGears\Blog\Model\Author;
use CommerceGears\Blog\Model\ResourceModel\Author as AuthorResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'author_id';

    protected function _construct()
    {
        $this->_init(Author::class, AuthorResource::class);
    }
}
