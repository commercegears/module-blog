<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Softaware\Blog\Model\ImportJob;

/**
 * Import job actions: View Log, Run Again (failed jobs) and Delete (not while running).
 */
class ImportJobActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        $name = (string)$this->getData('name');
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            $id = (int)$item['job_id'];
            $actions = [
                'view' => [
                    'href' => $this->urlBuilder->getUrl('softaware_blog/import/view', ['id' => $id]),
                    'label' => __('View Log'),
                ],
            ];
            if (($item['status'] ?? '') === ImportJob::STATUS_FAILED) {
                $actions['retry'] = [
                    'href' => $this->urlBuilder->getUrl('softaware_blog/import/retry', ['id' => $id]),
                    'label' => __('Run Again'),
                    'post' => true,
                ];
            }
            if (($item['status'] ?? '') !== ImportJob::STATUS_RUNNING) {
                $actions['delete'] = [
                    'href' => $this->urlBuilder->getUrl('softaware_blog/import/delete', ['id' => $id]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete Import Job'),
                        'message' => __('Delete this import job and its uploaded file? Imported posts are kept.'),
                    ],
                    'post' => true,
                ];
            }
            $dataSource['data']['items'][$index][$name] = $actions;
        }
        return $dataSource;
    }
}
