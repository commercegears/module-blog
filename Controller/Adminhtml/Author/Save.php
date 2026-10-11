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
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Softaware\Blog\Model\AuthorFactory;
use Softaware\Blog\Model\ImageUploader;
use Softaware\Blog\Model\ResourceModel\Author as AuthorResource;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Blog::authors';
    public const PERSIST_KEY = 'softaware_blog_author_form_data_source';
    private const FIELDS = ['name', 'url_key', 'job_title', 'bio', 'website_url', 'linkedin_url', 'x_url', 'meta_title', 'meta_description'];

    public function __construct(
        Action\Context $context,
        private readonly AuthorFactory $authorFactory,
        private readonly AuthorResource $authorResource,
        private readonly ImageUploader $imageUploader,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $data = (array)$this->getRequest()->getPostValue();
        $redirect = $this->resultRedirectFactory->create();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }
        $id = (int)($data['author_id'] ?? 0);
        try {
            $author = $this->authorFactory->create();
            if ($id) {
                $this->authorResource->load($author, $id);
                if (!$author->getId()) {
                    throw new LocalizedException(__('This author no longer exists.'));
                }
            }
            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $author->setData($field, trim((string)$data[$field]));
                }
            }
            $author->setData('is_active', empty($data['is_active']) ? 0 : 1);
            $author->setData('photo', $this->imageUploader->fromUiValue($data['photo'] ?? null));
            $this->authorResource->save($author);
            $this->dataPersistor->clear(self::PERSIST_KEY);
            $this->messageManager->addSuccessMessage(__('The author has been saved.'));
            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['id' => $author->getId()]);
            }
            return $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('The author could not be saved.'));
        }
        $this->dataPersistor->set(self::PERSIST_KEY, $data);
        return $redirect->setPath($id ? '*/*/edit' : '*/*/new', array_filter(['id' => $id]));
    }
}
