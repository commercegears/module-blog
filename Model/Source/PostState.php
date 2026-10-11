<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * State of a post in the posts grid: draft, scheduled (published with a future date) or published.
 */
class PostState implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'draft', 'label' => __('Draft')],
            ['value' => 'scheduled', 'label' => __('Scheduled')],
            ['value' => 'published', 'label' => __('Published')],
        ];
    }
}
