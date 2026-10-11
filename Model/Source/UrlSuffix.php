<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Source;

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
