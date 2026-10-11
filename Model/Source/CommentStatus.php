<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Softaware\Blog\Model\Comment;

class CommentStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Comment::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => Comment::STATUS_APPROVED, 'label' => __('Approved')],
            ['value' => Comment::STATUS_REJECTED, 'label' => __('Rejected')],
        ];
    }

    public function getLabel(int $status): string
    {
        foreach ($this->toOptionArray() as $option) {
            if ((int)$option['value'] === $status) {
                return (string)$option['label'];
            }
        }
        return '';
    }
}
