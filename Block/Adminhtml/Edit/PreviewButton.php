<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block\Adminhtml\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use CommerceGears\Blog\Model\PostFactory;
use CommerceGears\Blog\Model\Preview;
use CommerceGears\Blog\Model\ResourceModel\Post as PostResource;
use CommerceGears\Blog\Model\Url;

/**
 * "View" (live posts) or "Preview" (drafts and scheduled posts, a signed link valid for 24 hours) in a new tab.
 */
class PreviewButton extends GenericButton implements ButtonProviderInterface
{
    public function __construct(
        Context $context,
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource,
        private readonly Url $url,
        private readonly Preview $preview,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function getButtonData(): array
    {
        if (!$this->getId()) {
            return [];
        }
        $storeId = $this->getStoreId();
        $post = $this->postFactory->create();
        $post->setStoreId($storeId);
        $this->postResource->load($post, $this->getId());
        if (!$post->getId()) {
            return [];
        }
        if (!$storeId) {
            $storeIds = array_values(array_filter($post->getStoreIds()));
            $storeId = $storeIds[0] ?? (int)$this->storeManager->getDefaultStoreView()?->getId();
            if ($storeId) {
                $post = $this->postFactory->create();
                $post->setStoreId($storeId);
                $this->postResource->load($post, $this->getId());
            }
        }
        $url = $this->url->getPostUrl($post, $storeId);
        $live = $post->isLive();
        if (!$live) {
            $url .= (str_contains($url, '?') ? '&' : '?') . Preview::PARAM . '=' . rawurlencode($this->preview->createToken((int)$post->getId()));
        }
        return [
            'label' => $live ? __('View') : __('Preview'),
            'class' => 'action-secondary',
            'on_click' => sprintf('window.open(%s, "_blank", "noopener");', json_encode($url)),
            'sort_order' => 30,
        ];
    }
}
