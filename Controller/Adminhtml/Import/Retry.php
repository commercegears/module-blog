<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Softaware\Blog\Model\ImportJob;
use Softaware\Blog\Model\ImportJobFactory;
use Softaware\Blog\Model\ResourceModel\ImportJob as ImportJobResource;

/**
 * Queues a failed job again; it continues where it stopped.
 */
class Retry extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Blog::import';

    public function __construct(
        Action\Context $context,
        private readonly ImportJobFactory $jobFactory,
        private readonly ImportJobResource $jobResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $job = $this->jobFactory->create();
        $this->jobResource->load($job, (int)$this->getRequest()->getParam('id'));
        if ($job->getId() && $job->getData('status') === ImportJob::STATUS_FAILED) {
            $job->setData('status', ImportJob::STATUS_QUEUED);
            $job->setData('finished_at', null);
            $this->jobResource->save($job);
            $this->messageManager->addSuccessMessage(__('Import job #%1 has been queued again.', $job->getId()));
        } else {
            $this->messageManager->addErrorMessage(__('Only failed import jobs can be run again.'));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
