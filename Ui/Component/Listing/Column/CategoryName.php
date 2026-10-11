<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Category name indented by its level (HTML cell), so the tree is visible when the grid is sorted by path.
 */
class CategoryName extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            $level = max(1, (int)($item['level'] ?? 1));
            $prefix = $level > 1
                ? '<span class="commercegears-blog-indent" aria-hidden="true">' . str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level - 2) . '&#8627;&nbsp;</span>'
                : '';
            $dataSource['data']['items'][$index][(string)$this->getData('name')] = $prefix . $this->escaper->escapeHtml((string)($item['name'] ?? ''));
        }
        return $dataSource;
    }
}
