<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use CommerceGears\Blog\Model\Category;
use CommerceGears\Blog\Model\CategoryFactory;
use CommerceGears\Blog\Model\ResourceModel\Category as CategoryResource;
use CommerceGears\Blog\Model\UrlKeyFormatter;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_Blog::categories';
    public const PERSIST_KEY = 'commercegears_blog_category_form_data_source';

    public function __construct(
        Action\Context $context,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryResource $categoryResource,
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
        $id = (int)($data['category_id'] ?? 0);
        // a new record is always created with its default values
        $storeId = $id ? (int)($data['store'] ?? 0) : 0;
        try {
            $category = $this->categoryFactory->create();
            if ($id) {
                $this->categoryResource->load($category, $id);
                if (!$category->getId()) {
                    throw new LocalizedException(__('This category no longer exists.'));
                }
            }
            $category->setData('parent_id', !empty($data['parent_id']) ? (int)$data['parent_id'] : null);
            $category->setData('position', (int)($data['position'] ?? 0));
            $category->setData('is_active', empty($data['is_active']) ? 0 : 1);
            if ($storeId === 0) {
                foreach (Category::STORE_FIELDS as $field) {
                    if (array_key_exists($field, $data)) {
                        $category->setData($field, is_string($data[$field]) ? trim($data[$field]) : $data[$field]);
                    }
                }
            } else {
                $useDefault = (array)($data['use_default'] ?? []);
                $values = [];
                foreach (Category::STORE_FIELDS as $field) {
                    $values[$field] = !empty($useDefault[$field]) || !array_key_exists($field, $data) ? null : trim((string)$data[$field]);
                }
                if ($values['url_key'] !== null) {
                    $key = $this->urlKeyFormatter->format($values['url_key'] ?: (string)$values['name']);
                    if ($key !== '' && $this->categoryResource->isUrlKeyTaken($key, $storeId, $id)) {
                        throw new LocalizedException(__('Another category already uses the URL key "%1".', $key));
                    }
                    $values['url_key'] = $key ?: null;
                }
                $category->setData('_store_content', [$storeId => $values]);
            }
            $this->categoryResource->save($category);
            $this->dataPersistor->clear(self::PERSIST_KEY);
            $this->messageManager->addSuccessMessage(__('The category has been saved.'));
            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', array_filter(['id' => $category->getId(), 'store' => $storeId]));
            }
            return $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('The category could not be saved.'));
        }
        $this->dataPersistor->set(self::PERSIST_KEY, $data);
        return $redirect->setPath($id ? '*/*/edit' : '*/*/new', array_filter(['id' => $id, 'store' => $storeId]));
    }
}
