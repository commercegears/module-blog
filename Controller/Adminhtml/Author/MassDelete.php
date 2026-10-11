<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Adminhtml\Author;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Blog::authors';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly \Softaware\Blog\Model\ResourceModel\Author\CollectionFactory $collectionFactory,
        private readonly \Softaware\Blog\Model\ResourceModel\Author $resource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $count = 0;
        foreach ($this->filter->getCollection($this->collectionFactory->create()) as $entity) {
            $this->resource->delete($entity);
            $count++;
        }
        $this->messageManager->addSuccessMessage(__('A total of %1 author(s) have been deleted.', $count));
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
