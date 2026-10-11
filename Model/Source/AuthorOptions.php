<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use CommerceGears\Blog\Model\ResourceModel\Author\CollectionFactory;

/**
 * Authors for the post form and the posts grid filter.
 */
class AuthorOptions implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->collectionFactory->create()->setOrder('name', 'ASC') as $author) {
            $options[] = [
                'value' => (int)$author->getId(),
                'label' => $author->getName() . ($author->isActive() ? '' : ' (' . __('inactive') . ')'),
            ];
        }
        return $options;
    }
}
