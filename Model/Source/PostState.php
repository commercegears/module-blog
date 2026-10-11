<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Source;

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
