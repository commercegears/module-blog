<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Comment;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Translate\Inline\StateInterface as InlineTranslation;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use CommerceGears\Blog\Model\Comment;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\Post;
use CommerceGears\Blog\Model\Url;

/**
 * E-mails the store about a new comment.
 */
class Notifier
{
    public function __construct(
        private readonly Config $config,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly TransportBuilder $transportBuilder,
        private readonly InlineTranslation $inlineTranslation,
        private readonly BackendUrl $backendUrl,
        private readonly Url $url,
        private readonly LoggerInterface $logger
    ) {
    }

    public function notify(Comment $comment, Post $post, int $storeId): bool
    {
        if (!$this->config->getFlag('comments/notify', $storeId)) {
            return false;
        }
        try {
            $this->buildTransport($comment, $post, $storeId)->sendMessage();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('CommerceGears_Blog comment notification failed: ' . $e->getMessage());
            return false;
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    public function getRecipient(int $storeId): string
    {
        return $this->config->getValue('comments/notify_email', $storeId)
            ?: (string)$this->scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function buildTransport(Comment $comment, Post $post, int $storeId): TransportInterface
    {
        $this->inlineTranslation->suspend();
        $vars = [
            'post_title' => $post->getTitle(),
            'post_url' => $this->url->getPostUrl($post, $storeId),
            'author_name' => $comment->getAuthorName(),
            'author_email' => (string)$comment->getData('author_email'),
            'comment_text' => $comment->getContent(),
            'status_label' => $comment->getStatus() === Comment::STATUS_APPROVED
                ? (string)__('Published')
                : (string)__('Waiting for review'),
            'admin_url' => $this->getAdminUrl((int)$comment->getId()),
        ];
        return $this->transportBuilder
            ->setTemplateIdentifier($this->config->getValue('comments/email_template', $storeId) ?: 'commercegears_blog_comments_email_template')
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars($vars)
            ->setFromByScope($this->config->getValue('comments/email_identity', $storeId) ?: 'general', $storeId)
            ->addTo($this->getRecipient($storeId))
            ->getTransport();
    }

    private function getAdminUrl(int $commentId): string
    {
        try {
            $this->backendUrl->turnOffSecretKey();
            $url = $this->backendUrl->getUrl('commercegears_blog/comment/edit', ['id' => $commentId]);
            $this->backendUrl->turnOnSecretKey();
            return $url;
        } catch (\Throwable) {
            return '';
        }
    }
}
