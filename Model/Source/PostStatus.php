<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Softaware\Blog\Model\Post;

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
