<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use CommerceGears\Blog\Model\ImportJobFactory;
use CommerceGears\Blog\Model\ResourceModel\ImportJob as ImportJobResource;

/**
 * Details and log of one import job.
 */
class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_Blog::import';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $pageFactory,
        private readonly ImportJobFactory $jobFactory,
        private readonly ImportJobResource $jobResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $job = $this->jobFactory->create();
        $this->jobResource->load($job, (int)$this->getRequest()->getParam('id'));
        if (!$job->getId()) {
            $this->messageManager->addErrorMessage(__('This import job no longer exists.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $page = $this->pageFactory->create();
        $page->setActiveMenu('CommerceGears_Blog::import');
        $page->getConfig()->getTitle()->prepend(__('Import Job #%1', $job->getId()));
        $block = $page->getLayout()->getBlock('commercegears.blog.import.view');
        if ($block) {
            $block->setData('job', $job);
        }
        return $page;
    }
}
