<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Robots implements OptionSourceInterface
{
    public const VALUES = ['INDEX,FOLLOW', 'NOINDEX,FOLLOW', 'INDEX,NOFOLLOW', 'NOINDEX,NOFOLLOW'];

    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::VALUES as $value) {
            $options[] = ['value' => $value, 'label' => str_replace(',', ', ', $value)];
        }
        return $options;
    }
}
