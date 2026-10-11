<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use CommerceGears\Blog\Model\ImportJob;
use CommerceGears\Blog\Model\ImportJobFactory;
use CommerceGears\Blog\Model\ResourceModel\ImportJob as ImportJobResource;

/**
 * Queues a failed job again; it continues where it stopped.
 */
class Retry extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_Blog::import';

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
