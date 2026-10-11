<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Comment;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Store\Model\StoreManagerInterface;
use CommerceGears\Blog\Model\Comment;
use CommerceGears\Blog\Model\CommentFactory;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\Frontend\Catalog;
use CommerceGears\Blog\Model\PostFactory;
use CommerceGears\Blog\Model\ResourceModel\Comment as CommentResource;
use CommerceGears\Blog\Model\ResourceModel\Post as PostResource;

/**
 * Validates and stores a comment sent from the storefront.
 */
class Submission
{
    public function __construct(
        private readonly Config $config,
        private readonly Guard $guard,
        private readonly Recaptcha $recaptcha,
        private readonly CommentFactory $commentFactory,
        private readonly CommentResource $commentResource,
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource,
        private readonly Catalog $catalog,
        private readonly CustomerSession $customerSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly RemoteAddress $remoteAddress,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly Notifier $notifier
    ) {
    }

    /**
     * @return array{success: bool, message: string, published: bool, comment?: array<string, string>}
     * @throws LocalizedException with a message for the visitor
     */
    public function submit(RequestInterface $request): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->areCommentsEnabled($storeId) || !$this->config->isFeatureEnabled($storeId)) {
            throw new LocalizedException(__('Comments are closed.'));
        }
        $post = $this->postFactory->create()->setStoreId($storeId);
        $this->postResource->load($post, (int)$request->getParam('post_id'));
        if (!$post->getId() || !$this->catalog->isVisible($post, $storeId) || !$post->areCommentsAllowed()) {
            throw new LocalizedException(__('Comments are closed for this post.'));
        }
        if ($this->guard->isHoneypotFilled((string)$request->getParam(Guard::HONEYPOT_FIELD))) {
            // Pretend success so that bots do not learn anything.
            return ['success' => true, 'published' => false, 'message' => (string)__('Thank you. Your comment will appear once it has been reviewed.')];
        }
        $loggedIn = $this->customerSession->isLoggedIn();
        if (!$loggedIn && !$this->config->getFlag('comments/allow_guests', $storeId)) {
            throw new LocalizedException(__('Please sign in to comment.'));
        }
        $text = trim(str_replace("\r\n", "\n", (string)$request->getParam('content')));
        $error = $this->guard->validateText(
            $text,
            $this->config->getInt('comments/min_length', 2, 1000, 10, $storeId),
            $this->config->getInt('comments/max_length', 50, 10000, 3000, $storeId)
        );
        if ($error !== null) {
            throw new LocalizedException(__($error));
        }
        if ($this->config->getValue('comments/consent_text', $storeId) !== '' && !$request->getParam('consent')) {
            throw new LocalizedException(__('Please tick the consent box.'));
        }
        if ($loggedIn) {
            $customer = $this->customerSession->getCustomer();
            $name = trim((string)$request->getParam('author_name')) ?: trim($customer->getFirstname() . ' ' . $customer->getLastname());
            $email = (string)$customer->getEmail();
        } else {
            $name = trim((string)$request->getParam('author_name'));
            $email = trim((string)$request->getParam('author_email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new LocalizedException(__('Please enter a valid e-mail address.'));
            }
        }
        $name = mb_substr(strip_tags($name), 0, 100);
        if ($name === '') {
            throw new LocalizedException(__('Please enter your name.'));
        }
        $ipHash = $this->guard->hashIp(
            (string)$this->remoteAddress->getRemoteAddress(),
            (string)$this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_CRYPT_KEY)
        );
        $limit = $this->config->getInt('comments/hourly_limit', 0, 1000, 5, $storeId);
        if ($limit > 0 && $ipHash !== null
            && $this->commentResource->countRecentByIpHash($ipHash, gmdate('Y-m-d H:i:s', time() - 3600)) >= $limit
        ) {
            throw new LocalizedException(__('You have sent several comments in a short time. Please try again later.'));
        }
        $this->recaptcha->validate($request);

        $status = $this->decideStatus($storeId, $loggedIn, $text);
        /** @var Comment $comment */
        $comment = $this->commentFactory->create();
        $comment->setData([
            'post_id' => (int)$post->getId(),
            'store_id' => $storeId,
            'customer_id' => $loggedIn ? (int)$this->customerSession->getCustomerId() : null,
            'author_name' => $name,
            'author_email' => $email,
            'content' => $text,
            'status' => $status,
            'ip_hash' => $ipHash,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->commentResource->save($comment);
        $this->notifier->notify($comment, $post, $storeId);

        $published = $status === Comment::STATUS_APPROVED;
        return [
            'success' => true,
            'published' => $published,
            'message' => $published
                ? (string)__('Thank you. Your comment has been published.')
                : (string)__('Thank you. Your comment will appear once it has been reviewed.'),
            'comment' => $published ? ['name' => $name, 'text' => $text] : [],
        ];
    }

    public function decideStatus(int $storeId, bool $loggedIn, string $text): int
    {
        $mode = $this->config->getAutoApprove($storeId);
        $auto = $mode === Config::AUTO_APPROVE_ALL || ($mode === Config::AUTO_APPROVE_CUSTOMERS && $loggedIn);
        if ($auto && $this->config->getFlag('comments/hold_links', $storeId) && $this->guard->containsLink($text)) {
            $auto = false;
        }
        return $auto ? Comment::STATUS_APPROVED : Comment::STATUS_PENDING;
    }
}
