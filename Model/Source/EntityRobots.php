<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Source;

/**
 * Robots choice on a post, category or tag: empty uses the configured default.
 */
class EntityRobots extends Robots
{
    public function toOptionArray(): array
    {
        return array_merge([['value' => '', 'label' => __('Use the default from the configuration')]], parent::toOptionArray());
    }
}
