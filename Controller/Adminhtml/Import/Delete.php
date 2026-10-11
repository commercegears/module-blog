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
use Softaware\Blog\Model\Import\JobRunner;
use Softaware\Blog\Model\ImportJob;
use Softaware\Blog\Model\ImportJobFactory;
use Softaware\Blog\Model\ResourceModel\ImportJob as ImportJobResource;

/**
 * Deletes an import job and its files (imported posts are kept).
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Blog::import';

    public function __construct(
        Action\Context $context,
        private readonly ImportJobFactory $jobFactory,
        private readonly ImportJobResource $jobResource,
        private readonly JobRunner $jobRunner
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $job = $this->jobFactory->create();
        $this->jobResource->load($job, (int)$this->getRequest()->getParam('id'));
        if (!$job->getId()) {
            $this->messageManager->addErrorMessage(__('This import job no longer exists.'));
        } elseif ($job->getData('status') === ImportJob::STATUS_RUNNING) {
            $this->messageManager->addErrorMessage(__('The job is running right now. Delete it once it has finished.'));
        } else {
            $this->jobRunner->delete($job);
            $this->messageManager->addSuccessMessage(__('The import job has been deleted. Imported posts are kept.'));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
