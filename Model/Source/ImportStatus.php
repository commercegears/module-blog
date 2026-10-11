<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use CommerceGears\Blog\Model\ImportJob;

class ImportStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => ImportJob::STATUS_QUEUED, 'label' => __('Queued')],
            ['value' => ImportJob::STATUS_RUNNING, 'label' => __('Running')],
            ['value' => ImportJob::STATUS_DONE, 'label' => __('Finished')],
            ['value' => ImportJob::STATUS_FAILED, 'label' => __('Failed')],
        ];
    }
}
