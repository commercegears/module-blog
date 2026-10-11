<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use CommerceGears\Blog\Model\ResourceModel\Category\CollectionFactory;

/**
 * Blog categories as an indented list (widget parameter, parent category select).
 */
class CategoryOptions implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('All categories')]];
        return array_merge($options, $this->getTreeOptions());
    }

    /**
     * @param int $excludeId category whose subtree is left out (a category cannot be its own parent)
     * @return array<int, array{value: int, label: string}>
     */
    public function getTreeOptions(int $excludeId = 0): array
    {
        $collection = $this->collectionFactory->create()->orderByTree();
        $children = [];
        $names = [];
        foreach ($collection as $category) {
            $id = (int)$category->getId();
            $names[$id] = $category->getName() . ($category->isActive() ? '' : ' (' . __('inactive') . ')');
            $children[(int)$category->getParentId()][] = $id;
        }
        $options = [];
        $walk = static function (int $parentId, int $depth) use (&$walk, &$options, $children, $names, $excludeId): void {
            foreach ($children[$parentId] ?? [] as $id) {
                if ($id === $excludeId) {
                    continue;
                }
                $options[] = ['value' => $id, 'label' => str_repeat("\u{00A0}\u{00A0}\u{00A0}", $depth) . $names[$id]];
                $walk($id, $depth + 1);
            }
        };
        $walk(0, 0);
        return $options;
    }
}
