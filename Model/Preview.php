<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;

/**
 * Signed preview links for drafts and scheduled posts: "<expiry>.<hmac>", valid for 24 hours.
 */
class Preview
{
    public const PARAM = 'swb_preview';
    private const LIFETIME = 86400;

    public function __construct(private readonly DeploymentConfig $deploymentConfig)
    {
    }

    public function createToken(int $postId, ?int $now = null): string
    {
        $expires = ($now ?? time()) + self::LIFETIME;
        return $expires . '.' . $this->sign($postId, $expires);
    }

    public function isValid(int $postId, string $token, ?int $now = null): bool
    {
        if ($postId <= 0 || !preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $token, $m)) {
            return false;
        }
        $expires = (int)$m[1];
        if ($expires < ($now ?? time())) {
            return false;
        }
        return hash_equals($this->sign($postId, $expires), $m[2]);
    }

    private function sign(int $postId, int $expires): string
    {
        $key = (string)$this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_CRYPT_KEY);
        return hash_hmac('sha256', 'commercegears_blog_preview|' . $postId . '|' . $expires, $key);
    }
}
