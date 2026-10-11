<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;
use Softaware\Blog\Model\ResourceModel\Category as CategoryResource;
use Softaware\Blog\Model\ResourceModel\Category\CollectionFactory;

class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Blog::categories';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly CategoryResource $categoryResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $active = (int)$this->getRequest()->getParam('status') === 1 ? 1 : 0;
        $count = 0;
        foreach ($this->filter->getCollection($this->collectionFactory->create()) as $category) {
            $category->setData('is_active', $active);
            $this->categoryResource->save($category);
            $count++;
        }
        $this->messageManager->addSuccessMessage($active
            ? __('A total of %1 categories have been activated.', $count)
            : __('A total of %1 categories have been deactivated.', $count));
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
