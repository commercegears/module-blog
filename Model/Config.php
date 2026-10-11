<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Softaware\Core\Api\LicenceStateInterface;

/**
 * Typed access to Stores > Configuration > Softaware > Blog (section softaware_blog).
 */
class Config
{
    public const SECTION = 'softaware_blog';
    public const PACKAGE = 'softaware/module-blog';
    public const RECAPTCHA_KEY = 'softaware_blog_comment';

    public const AUTO_APPROVE_NEVER = 'never';
    public const AUTO_APPROVE_CUSTOMERS = 'customers';
    public const AUTO_APPROVE_ALL = 'all';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LicenceStateInterface $licenceState
    ) {
    }

    public function getValue(string $path, ?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(self::SECTION . '/' . $path, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::SECTION . '/' . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getInt(string $path, int $min, int $max, int $default, ?int $storeId = null): int
    {
        $value = $this->getValue($path, $storeId);
        if ($value === '' || !is_numeric($value)) {
            return $default;
        }
        return max($min, min($max, (int)$value));
    }

    /**
     * Storefront pages and widgets. Existing content stays readable whatever the licence state.
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('general/enabled', $storeId);
    }

    /**
     * New actions (comments, imports) also respect the licence state.
     */
    public function isFeatureEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->licenceState->isFeatureEnabled(self::PACKAGE);
    }

    public function getTitle(?int $storeId = null): string
    {
        return $this->getValue('general/title', $storeId) ?: (string)__('Blog');
    }

    public function getRoute(?int $storeId = null): string
    {
        return trim($this->getValue('general/route', $storeId), '/') ?: 'blog';
    }

    public function getUrlSuffix(?int $storeId = null): string
    {
        $suffix = $this->getValue('general/url_suffix', $storeId);
        return in_array($suffix, ['', '.html', '/'], true) ? $suffix : '';
    }

    public function getPrefix(string $type, ?int $storeId = null): string
    {
        return trim($this->getValue('general/' . $type . '_prefix', $storeId), '/') ?: $type;
    }

    public function getPostsPerPage(?int $storeId = null): int
    {
        return $this->getInt('list/posts_per_page', 1, 100, 10, $storeId);
    }

    public function getExcerptLength(?int $storeId = null): int
    {
        return $this->getInt('list/excerpt_length', 50, 1000, 250, $storeId);
    }

    public function getWordsPerMinute(?int $storeId = null): int
    {
        return $this->getInt('post/words_per_minute', 100, 400, 200, $storeId);
    }

    public function getRobots(string $type, ?int $storeId = null): string
    {
        $value = strtoupper(str_replace(' ', '', $this->getValue('seo/robots_' . $type, $storeId)));
        return $value !== '' ? $value : 'INDEX,FOLLOW';
    }

    public function areCommentsEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->getFlag('comments/enabled', $storeId);
    }

    public function getAutoApprove(?int $storeId = null): string
    {
        $value = $this->getValue('comments/auto_approve', $storeId);
        return in_array($value, [self::AUTO_APPROVE_CUSTOMERS, self::AUTO_APPROVE_ALL], true)
            ? $value
            : self::AUTO_APPROVE_NEVER;
    }
}
