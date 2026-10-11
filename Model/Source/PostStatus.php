<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use CommerceGears\Blog\Model\Post;

class PostStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Post::STATUS_DRAFT, 'label' => __('Draft')],
            ['value' => Post::STATUS_PUBLISHED, 'label' => __('Published')],
        ];
    }
}
