<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use CommerceGears\Blog\Model\Config;

class AutoApprove implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::AUTO_APPROVE_NEVER, 'label' => __('Never (review every comment)')],
            ['value' => Config::AUTO_APPROVE_CUSTOMERS, 'label' => __('Comments from signed-in customers')],
            ['value' => Config::AUTO_APPROVE_ALL, 'label' => __('All comments')],
        ];
    }
}
