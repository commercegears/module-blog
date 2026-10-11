<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Route\ConfigInterface as RouteConfig;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Blog path and URL prefixes: lower case letters, digits and hyphens; the blog path may have several levels.
 */
class UrlPart extends Value
{
    /** First path segments Magento serves itself (besides the storefront routes) */
    private const RESERVED = ['admin', 'media', 'static', 'pub', 'errors', 'setup', 'rest', 'soap', 'graphql', 'index.php'];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly RouteConfig $routeConfig,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        $value = strtolower(trim((string)$this->getValue(), " /\t\n\r"));
        $multiLevel = $this->getField() === 'route';
        $pattern = $multiLevel ? '#^[a-z0-9][a-z0-9-]*(/[a-z0-9][a-z0-9-]*)*$#' : '#^[a-z0-9][a-z0-9-]*$#';
        if ($value === '' || !preg_match($pattern, $value)) {
            throw new LocalizedException(__(
                '"%1" is not a valid URL part. Use lower case letters, digits and hyphens%2.',
                (string)$this->getValue(),
                $multiLevel ? (string)__(', with a slash between levels') : ''
            ));
        }
        if ($multiLevel) {
            $first = explode('/', $value)[0];
            // the blog router runs after Magento's own routers, so a blog path such as "checkout" would never be reached
            if (in_array($first, self::RESERVED, true) || $this->routeConfig->getRouteByFrontName($first, 'frontend')) {
                throw new LocalizedException(__(
                    'The blog path cannot start with "%1": Magento or an extension already uses that address.',
                    $first
                ));
            }
        }
        $this->setValue($value);
        return parent::beforeSave();
    }
}
