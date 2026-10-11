<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block\Post;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template\Context;
use CommerceGears\Blog\Block\AbstractBlog;
use CommerceGears\Blog\Model\Comment;
use CommerceGears\Blog\Model\Comment\Guard;
use CommerceGears\Blog\Model\Comment\Recaptcha;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\Frontend\BlockContext;
use CommerceGears\Blog\Model\Post;
use CommerceGears\Blog\Model\ResourceModel\Comment\CollectionFactory;

/**
 * Approved comments (with store replies) and the comment form. Cached with the page: visitor data
 * (signed in or not, name) is filled in by JavaScript from the customer section.
 */
class Comments extends AbstractBlog implements IdentityInterface
{
    /** @var array<int, array{comment: Comment, replies: Comment[]}>|null */
    private ?array $threads = null;

    public function __construct(
        Context $context,
        BlockContext $blogContext,
        private readonly CollectionFactory $collectionFactory,
        private readonly Recaptcha $recaptcha,
        array $data = []
    ) {
        parent::__construct($context, $blogContext, $data);
    }

    public function getPost(): ?Post
    {
        return $this->currentPage->getPost();
    }

    public function isEnabled(): bool
    {
        $post = $this->getPost();
        return $post !== null && !$this->currentPage->isPreview()
            && $this->config->areCommentsEnabled($this->getStoreId()) && $post->areCommentsAllowed();
    }

    /**
     * @return array<int, array{comment: Comment, replies: Comment[]}>
     */
    public function getThreads(): array
    {
        if ($this->threads === null) {
            $this->threads = [];
            $collection = $this->collectionFactory->create()
                ->addFieldToFilter('post_id', (int)$this->getPost()->getId())
                ->addFieldToFilter('status', Comment::STATUS_APPROVED)
                ->setOrder('created_at', 'ASC')
                ->setOrder('comment_id', 'ASC');
            $replies = [];
            foreach ($collection as $comment) {
                $parentId = (int)$comment->getData('parent_id');
                if ($parentId) {
                    $replies[$parentId][] = $comment;
                } else {
                    $this->threads[(int)$comment->getId()] = ['comment' => $comment, 'replies' => []];
                }
            }
            foreach ($replies as $parentId => $items) {
                if (isset($this->threads[$parentId])) {
                    $this->threads[$parentId]['replies'] = $items;
                }
            }
        }
        return $this->threads;
    }

    public function getCount(): int
    {
        $count = 0;
        foreach ($this->getThreads() as $thread) {
            $count += 1 + count($thread['replies']);
        }
        return $count;
    }

    public function formatCommentDate(Comment $comment): string
    {
        try {
            return (string)$this->_localeDate->formatDateTime(
                new \DateTime((string)$comment->getData('created_at'), new \DateTimeZone('UTC')),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::NONE
            );
        } catch (\Exception) {
            return '';
        }
    }

    public function getCommentHtml(Comment $comment): string
    {
        return nl2br($this->escapeHtml($comment->getContent()));
    }

    public function getReplyName(Comment $comment): string
    {
        if ((int)$comment->getData('is_admin_reply')) {
            return $this->config->getValue('comments/reply_name', $this->getStoreId())
                ?: (string)$this->_scopeConfig->getValue('general/store_information/name', 'store', $this->getStoreId())
                ?: (string)__('Store reply');
        }
        return $comment->getAuthorName();
    }

    public function areGuestsAllowed(): bool
    {
        return $this->config->getFlag('comments/allow_guests', $this->getStoreId());
    }

    public function getConsentHtml(): string
    {
        $text = $this->config->getValue('comments/consent_text', $this->getStoreId());
        return $text === '' ? '' : $this->escapeHtml($text, ['a', 'strong', 'em', 'b', 'i']);
    }

    public function getMinLength(): int
    {
        return $this->config->getInt('comments/min_length', 2, 1000, 10, $this->getStoreId());
    }

    public function getMaxLength(): int
    {
        return $this->config->getInt('comments/max_length', 50, 10000, 3000, $this->getStoreId());
    }

    public function getLoginUrl(): string
    {
        return $this->getUrl('customer/account/login');
    }

    public function getHoneypotField(): string
    {
        return Guard::HONEYPOT_FIELD;
    }

    public function isRecaptchaEnabled(): bool
    {
        return $this->recaptcha->isEnabled();
    }

    public function getRecaptchaKey(): string
    {
        return Config::RECAPTCHA_KEY;
    }

    /**
     * Luma: Magento's reCAPTCHA widget for the form (empty when reCAPTCHA is not installed or not configured).
     */
    public function getRecaptchaHtml(): string
    {
        if (!$this->isRecaptchaEnabled()) {
            return '';
        }
        try {
            $block = $this->getLayout()->createBlock(
                'Magento\ReCaptchaUi\Block\ReCaptcha',
                'commercegears.blog.comment.recaptcha',
                ['data' => [
                    'recaptcha_for' => Config::RECAPTCHA_KEY,
                    'jsLayout' => ['components' => ['recaptcha' => [
                        'component' => 'Magento_ReCaptchaFrontendUi/js/reCaptcha',
                        'reCaptchaId' => 'commercegears-blog-comment-recaptcha',
                    ]]],
                ]]
            );
            $block->setTemplate('Magento_ReCaptchaFrontendUi::recaptcha.phtml');
            return $block->toHtml();
        } catch (\Throwable $e) {
            $this->_logger->warning('CommerceGears_Blog reCAPTCHA block: ' . $e->getMessage());
            return '';
        }
    }

    public function getJsConfig(): string
    {
        return (string)json_encode([
            'url' => $this->getUrl('commercegears_blog/comment/post'),
            'guests' => $this->areGuestsAllowed(),
            'text' => [
                'error' => (string)__('Sorry, the comment could not be sent. Please try again.'),
                'sending' => (string)__('Sending...'),
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    public function getIdentities()
    {
        return $this->getPost() ? [Post::CACHE_TAG . '_' . $this->getPost()->getId()] : [];
    }
}
