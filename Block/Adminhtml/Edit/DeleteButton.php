<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block\Adminhtml\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        if (!$this->getId() || !$this->context->getAuthorization()->isAllowed($this->getDeleteResource())) {
            return [];
        }
        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => 'deleteConfirm(' . json_encode((string)__('Delete this %1? This cannot be undone.', $this->getEntityLabel()))
                . ', ' . json_encode($this->getUrl('*/*/delete', ['id' => $this->getId()])) . ', {data: {}})',
            'sort_order' => 20,
        ];
    }
}
