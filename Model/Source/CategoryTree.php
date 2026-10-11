<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Source;

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
