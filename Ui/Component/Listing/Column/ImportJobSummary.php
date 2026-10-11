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
 * Progress and result counts of an import job (plain text).
 */
class ImportJobSummary extends Column
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
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            $stats = json_decode((string)($item['stats'] ?? ''), true) ?: [];
            $done = (int)($stats['posts_created'] ?? 0) + (int)($stats['posts_updated'] ?? 0) + (int)($stats['posts_skipped'] ?? 0);
            $parts = [];
            if (isset($stats['posts_total'])) {
                $parts[] = (string)__('%1 of %2 posts', $done, (int)$stats['posts_total']);
            } else {
                $parts[] = (string)__('Waiting to start');
            }
            foreach ([
                'posts_created' => 'created', 'posts_updated' => 'updated', 'posts_skipped' => 'skipped',
                'comments' => 'comments', 'images' => 'images', 'redirects' => 'redirects', 'errors' => 'errors',
            ] as $key => $label) {
                if (!empty($stats[$key])) {
                    $parts[] = (int)$stats[$key] . ' ' . __($label);
                }
            }
            $dataSource['data']['items'][$index][$name] = implode(', ', $parts);
        }
        return $dataSource;
    }
}
