<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\ResourceModel\ImportJob;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Softaware\Blog\Model\ImportJob;
use Softaware\Blog\Model\ResourceModel\ImportJob as ImportJobResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'job_id';

    protected function _construct()
    {
        $this->_init(ImportJob::class, ImportJobResource::class);
    }
}
