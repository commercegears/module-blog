<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block\Adminhtml\Import;

use Magento\Backend\Block\Template;
use Magento\Framework\File\Size as FileSize;
use Magento\Store\Model\System\Store as SystemStore;

/**
 * Upload form of the WordPress import page.
 */
class Form extends Template
{
    protected $_template = 'Softaware_Blog::import/form.phtml';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly SystemStore $systemStore,
        private readonly FileSize $fileSize,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getUploadUrl(): string
    {
        return $this->getUrl('softaware_blog/import/upload');
    }

    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    public function getStoreOptions(): array
    {
        return $this->systemStore->getStoreValuesForForm(false, true);
    }

    public function isSingleStoreMode(): bool
    {
        return $this->_storeManager->isSingleStoreMode();
    }

    public function getMaxUploadMb(): string
    {
        return (string)$this->fileSize->getMaxFileSizeInMb();
    }
}
