<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Blog categories as an indented list without an "all" entry (grid filters, post form).
 */
class CategoryTree implements OptionSourceInterface
{
    public function __construct(private readonly CategoryOptions $categoryOptions)
    {
    }

    public function toOptionArray(): array
    {
        return $this->categoryOptions->getTreeOptions();
    }
}
