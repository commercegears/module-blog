<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Softaware\Blog\Model\Config;

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
