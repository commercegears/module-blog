<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\ResourceModel\ImportJob;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use CommerceGears\Blog\Model\ImportJob;
use CommerceGears\Blog\Model\ResourceModel\ImportJob as ImportJobResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'job_id';

    protected function _construct()
    {
        $this->_init(ImportJob::class, ImportJobResource::class);
    }
}
