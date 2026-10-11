<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

use Magento\Framework\Model\AbstractModel;
use CommerceGears\Blog\Model\ResourceModel\ImportJob as ImportJobResource;

class ImportJob extends AbstractModel
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected function _construct()
    {
        $this->_init(ImportJobResource::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        $options = json_decode((string)$this->getData('options'), true);
        return is_array($options) ? $options : [];
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        $stats = json_decode((string)$this->getData('stats'), true);
        return is_array($stats) ? $stats : [];
    }
}
