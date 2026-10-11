<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Adminhtml\Post;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use CommerceGears\Blog\Api\PostRepositoryInterface;
use CommerceGears\Blog\Model\ImageUploader;
use CommerceGears\Blog\Model\Post;
use CommerceGears\Blog\Model\PostFactory;
use CommerceGears\Blog\Model\ResourceModel\Post as PostResource;
use CommerceGears\Blog\Model\ResourceModel\Tag as TagResource;
use CommerceGears\Blog\Model\TagFactory;
use CommerceGears\Blog\Model\UrlKeyFormatter;

/**
 * Saves a post: the default values, or (with a store view) the store view's own values.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_Blog::posts_save';
    public const PERSIST_KEY = 'commercegears_blog_post_form_data_source';

    public function __construct(
        Action\Context $context,
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource,
        private readonly PostRepositoryInterface $postRepository,
        private readonly TagFactory $tagFactory,
        private readonly TagResource $tagResource,
        private readonly ImageUploader $imageUploader,
        private readonly UrlKeyFormatter $urlKeyFormatter,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly TimezoneInterface $timezone
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
        $id = (int)($data['post_id'] ?? 0);
        // a new record is always created with its default values
        $storeId = $id ? (int)($data['store'] ?? 0) : 0;
        try {
            $post = $this->postFactory->create();
            if ($id) {
                $this->postResource->load($post, $id);
                if (!$post->getId()) {
                    throw new LocalizedException(__('This post no longer exists.'));
                }
            }
            $this->applyGlobalValues($post, $data, $id);
            if ($storeId === 0) {
                foreach (Post::STORE_FIELDS as $field) {
                    if (array_key_exists($field, $data)) {
                        $post->setData($field, is_string($data[$field]) ? trim($data[$field]) : $data[$field]);
                    }
                }
            } else {
                $post->setData('_store_content', [$storeId => $this->getStoreValues($data, $storeId, $id)]);
            }
            $this->postRepository->save($post);
            $this->dataPersistor->clear(self::PERSIST_KEY);
            $this->messageManager->addSuccessMessage(
                $post->isScheduled()
                    ? __('The post has been saved. It goes live on %1.', $this->formatDate((string)$post->getPublishAt()))
                    : __('The post has been saved.')
            );
            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', array_filter(['id' => $post->getId(), 'store' => $storeId]));
            }
            return $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('The post could not be saved.'));
        }
        $this->dataPersistor->set(self::PERSIST_KEY, $data);
        return $redirect->setPath($id ? '*/*/edit' : '*/*/new', array_filter(['id' => $id, 'store' => $storeId]));
    }

    /**
     * Settings that are the same in every store view.
     *
     * @param array<string, mixed> $data
     */
    private function applyGlobalValues(Post $post, array $data, int $id): void
    {
        $canPublish = $this->_authorization->isAllowed('CommerceGears_Blog::posts_publish');
        if ($canPublish) {
            $post->setStatus((int)($data['status'] ?? Post::STATUS_DRAFT));
            $post->setPublishAt($this->normaliseDate((string)($data['publish_at'] ?? '')));
        } elseif (!$id) {
            $post->setStatus(Post::STATUS_DRAFT);
            $post->setPublishAt(null);
        }
        $post->setData('is_featured', empty($data['is_featured']) ? 0 : 1);
        $post->setData('allow_comments', empty($data['allow_comments']) ? 0 : 1);
        $post->setAuthorId(!empty($data['author_id']) ? (int)$data['author_id'] : null);
        $post->setData('featured_image', $this->imageUploader->fromUiValue($data['featured_image'] ?? null));
        $canonical = trim((string)($data['canonical_url'] ?? ''));
        if ($canonical !== '' && !preg_match('#^https?://#i', $canonical)) {
            throw new LocalizedException(__('The canonical URL must be a full address starting with https://.'));
        }
        $post->setData('canonical_url', $canonical !== '' ? $canonical : null);
        $post->setStoreIds(array_map('intval', (array)($data['store_ids'] ?? [0])));
        $post->setCategoryIds(array_map('intval', array_filter((array)($data['category_ids'] ?? []))));
        $post->setTagIds($this->resolveTags((string)($data['tag_names'] ?? '')));
        $post->setProductIds($this->getProductIds($data));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string|null> NULL = use the default value
     */
    private function getStoreValues(array $data, int $storeId, int $postId): array
    {
        $useDefault = (array)($data['use_default'] ?? []);
        $values = [];
        foreach (Post::STORE_FIELDS as $field) {
            $values[$field] = !empty($useDefault[$field]) || !array_key_exists($field, $data)
                ? null
                : trim((string)$data[$field]);
        }
        if ($values['url_key'] !== null) {
            $key = $this->urlKeyFormatter->format($values['url_key'] !== '' ? $values['url_key'] : (string)$values['title']);
            if ($key !== '') {
                $this->postResource->assertUrlKeyAvailable($key, $storeId, $postId);
            }
            $values['url_key'] = $key !== '' ? $key : null;
        }
        if ($values['title'] === '') {
            $values['title'] = null;
        }
        return $values;
    }

    /**
     * @return int[] tag IDs; tags that do not exist yet are created
     */
    private function resolveTags(string $names): array
    {
        $list = array_filter(array_map('trim', explode(',', $names)), static fn ($name) => $name !== '');
        return $this->tagResource->resolveNames($list, function (string $name): int {
            $tag = $this->tagFactory->create();
            $tag->setData('name', mb_substr($name, 0, 255));
            $this->tagResource->save($tag);
            return (int)$tag->getId();
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return int[] related product IDs in the order of their position
     */
    private function getProductIds(array $data): array
    {
        $rows = (array)($data['links']['related'] ?? []);
        usort($rows, static fn ($a, $b) => (int)($a['position'] ?? 0) <=> (int)($b['position'] ?? 0));
        return array_values(array_unique(array_filter(array_map(static fn ($row) => (int)($row['id'] ?? 0), $rows))));
    }

    /**
     * The date field sends the time in UTC (ISO 8601); dates without a time zone are taken as UTC.
     */
    private function normaliseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            $date = new \DateTime($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new LocalizedException(__('Please enter a valid publish date.'));
        }
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function formatDate(string $utc): string
    {
        try {
            return $this->timezone->formatDateTime(new \DateTime($utc, new \DateTimeZone('UTC')), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
        } catch (\Exception) {
            return $utc;
        }
    }
}
