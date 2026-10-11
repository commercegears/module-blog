<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;
use CommerceGears\Blog\Model\ResourceModel\Post as PostResource;

/**
 * Scheduled posts become visible by date; this refreshes the cached pages (lists, sidebar, widgets, sitemap
 * data) once their publish date has passed.
 */
class SchedulePublisher
{
    public function __construct(
        private readonly PostResource $postResource,
        private readonly PostFactory $postFactory,
        private readonly CacheInterface $cache,
        private readonly ManagerInterface $eventManager
    ) {
    }

    /**
     * @return int[] IDs of the posts that went live
     */
    public function run(): array
    {
        $ids = $this->postResource->getDueScheduledIds(gmdate('Y-m-d H:i:s'));
        foreach ($ids as $id) {
            $post = $this->postFactory->create();
            $this->postResource->load($post, $id);
            if (!$post->getId()) {
                continue;
            }
            $post->setData('_affected_product_ids', $post->getProductIds());
            $this->cache->clean($post->getIdentities());
            // full-page cache (built-in and Varnish) listens to this event
            $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $post]);
        }
        $this->postResource->clearSchedulePending($ids);
        return $ids;
    }
}
