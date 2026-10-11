<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class UrlSuffix implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('None (/blog/my-post)')],
            ['value' => '.html', 'label' => __('.html (/blog/my-post.html)')],
            ['value' => '/', 'label' => __('Slash (/blog/my-post/)')],
        ];
    }
}
