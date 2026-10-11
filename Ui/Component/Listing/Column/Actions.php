<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Edit and Delete row actions. Configured in the listing XML: config/editUrlPath, config/deleteUrlPath,
 * config/deleteResource (ACL), config/entityLabel and config/titleField (shown in the delete confirmation).
 */
class Actions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $config = (array)$this->getData('config');
        $idField = (string)($config['indexField'] ?? 'id');
        $titleField = (string)($config['titleField'] ?? '');
        $canDelete = !empty($config['deleteUrlPath'])
            && (empty($config['deleteResource']) || $this->authorization->isAllowed((string)$config['deleteResource']));
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item[$idField])) {
                continue;
            }
            $name = $this->getData('name');
            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl((string)$config['editUrlPath'], ['id' => $item[$idField]]),
                'label' => __('Edit'),
            ];
            if ($canDelete) {
                $title = $titleField !== '' ? (string)($item[$titleField] ?? '') : '';
                $item[$name]['delete'] = [
                    'href' => $this->urlBuilder->getUrl((string)$config['deleteUrlPath'], ['id' => $item[$idField]]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete %1', $this->escaper->escapeHtml((string)($config['entityLabel'] ?? ''))),
                        'message' => $title !== ''
                            ? __('Delete "%1"? This cannot be undone.', $this->escaper->escapeHtml(mb_substr($title, 0, 80)))
                            : __('Delete this record? This cannot be undone.'),
                    ],
                    'post' => true,
                ];
            }
        }
        return $dataSource;
    }
}
