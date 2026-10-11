<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Source;

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
