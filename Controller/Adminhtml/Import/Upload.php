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
use Magento\Framework\Exception\LocalizedException;
use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\Import\FileStorage;
use Softaware\Blog\Model\Import\JobRunner;

/**
 * Stores an uploaded WordPress export file and queues the import (cron runs it in the background).
 */
class Upload extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Blog::import';

    public function __construct(
        Action\Context $context,
        private readonly FileStorage $fileStorage,
        private readonly JobRunner $jobRunner,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');
        try {
            if (!$this->config->isFeatureEnabled()) {
                throw new LocalizedException(__('Imports are not available: the blog is disabled or its licence is not active.'));
            }
            $file = $this->getRequest()->getFiles('import_file');
            $file = is_array($file) ? $file : [];
            $this->validate($file);
            $fileName = $this->fileStorage->storeUpload((string)$file['tmp_name']);
            $request = $this->getRequest();
            $user = $this->_auth->getUser();
            $job = $this->jobRunner->queue($fileName, (string)$file['name'], [
                'store_ids' => (array)$request->getParam('store_ids', [0]),
                'download_images' => (bool)$request->getParam('download_images'),
                'redirects' => (bool)$request->getParam('redirects'),
                'comments' => (bool)$request->getParam('comments'),
                'as_drafts' => (bool)$request->getParam('as_drafts'),
                'update_existing' => (bool)$request->getParam('update_existing'),
            ], $user ? (string)$user->getUserName() : '');
            $this->messageManager->addSuccessMessage(__(
                'The import has been queued as job #%1. It runs in the background with cron, usually within a minute; this page shows the progress.',
                $job->getId()
            ));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('The file could not be imported.'));
        }
        return $redirect;
    }

    /**
     * @param array<string, mixed> $file
     * @throws LocalizedException
     */
    private function validate(array $file): void
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new LocalizedException(__('The file is larger than the server accepts for uploads. Split the export in WordPress or import it on the command line.'));
        }
        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new LocalizedException(__('Please choose a WordPress export file (.xml).'));
        }
        if (strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'xml') {
            throw new LocalizedException(__('Please upload the .xml file that WordPress creates under Tools > Export.'));
        }
        $handle = fopen((string)$file['tmp_name'], 'rb');
        $head = $handle ? (string)fread($handle, 8192) : '';
        if ($handle) {
            fclose($handle);
        }
        if (stripos($head, '<!DOCTYPE') !== false || stripos($head, '<!ENTITY') !== false) {
            throw new LocalizedException(__('The file contains a document type declaration, which WordPress exports never have.'));
        }
        if (stripos($head, '<rss') === false || (stripos($head, 'wordpress.org/export') === false && stripos($head, 'wp:') === false)) {
            throw new LocalizedException(__('This is not a WordPress export file. In WordPress, use Tools > Export > All content.'));
        }
    }
}
