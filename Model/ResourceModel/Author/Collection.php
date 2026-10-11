<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\ResourceModel\Author;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Softaware\Blog\Model\Author;
use Softaware\Blog\Model\ResourceModel\Author as AuthorResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'author_id';

    protected function _construct()
    {
        $this->_init(Author::class, AuthorResource::class);
    }
}
