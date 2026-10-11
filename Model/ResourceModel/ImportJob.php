<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ImportJob extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('softaware_blog_import_job', 'job_id');
    }

    /**
     * Marks a queued job as running; false when another process took it first.
     */
    public function claim(int $jobId): bool
    {
        return $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => 'running', 'started_at' => gmdate('Y-m-d H:i:s')],
            ['job_id = ?' => $jobId, 'status = ?' => 'queued']
        ) === 1;
    }
}
