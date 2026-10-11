<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Ui\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Softaware\Blog\Model\CommentFactory;
use Softaware\Blog\Model\ResourceModel\Comment as CommentResource;
use Softaware\Blog\Model\ResourceModel\Comment\CollectionFactory;
use Softaware\Blog\Model\ResourceModel\Post as PostResource;

class CommentFormDataProvider extends AbstractFormDataProvider
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        RequestInterface $request,
        DataPersistorInterface $dataPersistor,
        CollectionFactory $collectionFactory,
        private readonly CommentFactory $commentFactory,
        private readonly CommentResource $commentResource,
        private readonly PostResource $postResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $request, $dataPersistor, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    protected function loadEntity(int $id, int $storeId): ?AbstractModel
    {
        $comment = $this->commentFactory->create();
        $this->commentResource->load($comment, $id);
        return $comment;
    }

    protected function prepareData(AbstractModel $entity, array $data): array
    {
        $connection = $this->postResource->getConnection();
        $title = (string)$connection->fetchOne(
            $connection->select()->from($this->postResource->getMainTable(), ['title'])
                ->where('post_id = ?', (int)$entity->getData('post_id'))
        );
        $data['post_title'] = $title;
        $data['post_link'] = $this->urlBuilder->getUrl('softaware_blog/post/edit', ['id' => (int)$entity->getData('post_id')]);
        $data['status'] = (string)(int)$entity->getData('status');
        $storeId = (int)$entity->getData('store_id');
        try {
            $data['store_name'] = $storeId ? (string)$this->storeManager->getStore($storeId)->getName() : (string)__('All Store Views');
        } catch (\Exception) {
            $data['store_name'] = '';
        }
        $data['author_email'] = (string)($data['author_email'] ?? '');
        $data['reply'] = '';
        return $data;
    }
}
