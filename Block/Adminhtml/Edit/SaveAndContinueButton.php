<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block\Adminhtml\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveAndContinueButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        return [
            'label' => __('Save and Continue Edit'),
            // Empty on_click: without it Magento adds a link to the current route (location.href) that races the save.
            'on_click' => '',
            'class' => 'save',
            'data_attribute' => ['mage-init' => ['button' => ['event' => 'saveAndContinueEdit']]],
            'sort_order' => 80,
        ];
    }
}
