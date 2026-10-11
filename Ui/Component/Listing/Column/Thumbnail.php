<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Softaware\Blog\Model\Url;

/**
 * Small featured image (HTML cell).
 */
class Thumbnail extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Url $url,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        $name = (string)$this->getData('name');
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            $file = (string)($item['featured_image'] ?? '');
            $dataSource['data']['items'][$index][$name] = $file === ''
                ? '<span class="softaware-blog-thumb" aria-hidden="true"></span>'
                : '<img class="softaware-blog-thumb" loading="lazy" alt="" src="'
                    . $this->escaper->escapeUrl($this->url->getMediaUrl($file)) . '">';
        }
        return $dataSource;
    }
}
