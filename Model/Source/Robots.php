<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Source;

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
