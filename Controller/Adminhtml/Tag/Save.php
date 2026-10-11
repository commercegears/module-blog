<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Adminhtml\Tag;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Softaware\Blog\Model\ResourceModel\Tag as TagResource;
use Softaware\Blog\Model\Tag;
use Softaware\Blog\Model\TagFactory;
use Softaware\Blog\Model\UrlKeyFormatter;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Blog::tags';
    public const PERSIST_KEY = 'softaware_blog_tag_form_data_source';

    public function __construct(
        Action\Context $context,
        private readonly TagFactory $tagFactory,
        private readonly TagResource $tagResource,
        private readonly UrlKeyFormatter $urlKeyFormatter,
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
        $id = (int)($data['tag_id'] ?? 0);
        // a new record is always created with its default values
        $storeId = $id ? (int)($data['store'] ?? 0) : 0;
        try {
            $tag = $this->tagFactory->create();
            if ($id) {
                $this->tagResource->load($tag, $id);
                if (!$tag->getId()) {
                    throw new LocalizedException(__('This tag no longer exists.'));
                }
            }
            if ($storeId === 0) {
                foreach (Tag::STORE_FIELDS as $field) {
                    if (array_key_exists($field, $data)) {
                        $tag->setData($field, is_string($data[$field]) ? trim($data[$field]) : $data[$field]);
                    }
                }
            } else {
                $useDefault = (array)($data['use_default'] ?? []);
                $values = [];
                foreach (Tag::STORE_FIELDS as $field) {
                    $values[$field] = !empty($useDefault[$field]) || !array_key_exists($field, $data) ? null : trim((string)$data[$field]);
                }
                if ($values['url_key'] !== null) {
                    $key = $this->urlKeyFormatter->format($values['url_key'] ?: (string)$values['name']);
                    if ($key !== '' && $this->tagResource->isUrlKeyTaken($key, $storeId, $id)) {
                        throw new LocalizedException(__('Another tag already uses the URL key "%1".', $key));
                    }
                    $values['url_key'] = $key ?: null;
                }
                $tag->setData('_store_content', [$storeId => $values]);
            }
            $this->tagResource->save($tag);
            $this->dataPersistor->clear(self::PERSIST_KEY);
            $this->messageManager->addSuccessMessage(__('The tag has been saved.'));
            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', array_filter(['id' => $tag->getId(), 'store' => $storeId]));
            }
            return $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('The tag could not be saved.'));
        }
        $this->dataPersistor->set(self::PERSIST_KEY, $data);
        return $redirect->setPath($id ? '*/*/edit' : '*/*/new', array_filter(['id' => $id, 'store' => $storeId]));
    }
}
