<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Shortened text (comment content) for the grid; the cell is rendered as text, so nothing is interpreted as HTML.
 */
class Excerpt extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        $name = (string)$this->getData('name');
        $length = (int)($this->getData('config/length') ?: 140);
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            $text = trim((string)preg_replace('/\s+/u', ' ', (string)($item[$name] ?? '')));
            if (mb_strlen($text) > $length) {
                $text = rtrim(mb_substr($text, 0, $length)) . '…';
            }
            $dataSource['data']['items'][$index][$name] = $text;
        }
        return $dataSource;
    }
}
