<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Comment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;
use CommerceGears\Blog\Model\Config;

/**
 * Optional Google reCAPTCHA through Magento's own reCAPTCHA modules (Magento_ReCaptchaUi and related).
 *
 * Those modules are optional, so their classes are only resolved at runtime once the module is known to be
 * enabled. The form key is "commercegears_blog_comment" (config path recaptcha_frontend/type_for/commercegears_blog_comment).
 */
class Recaptcha
{
    private const IS_ENABLED = 'Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface';
    private const RESPONSE_RESOLVER = 'Magento\ReCaptchaUi\Model\CaptchaResponseResolverInterface';
    private const CONFIG_RESOLVER = 'Magento\ReCaptchaUi\Model\ValidationConfigResolverInterface';
    private const VALIDATOR = 'Magento\ReCaptchaValidationApi\Api\ValidatorInterface';

    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly ObjectManagerInterface $objectManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function isEnabled(): bool
    {
        if (!$this->moduleManager->isEnabled('Magento_ReCaptchaUi')
            || !$this->moduleManager->isEnabled('Magento_ReCaptchaValidationApi')
        ) {
            return false;
        }
        try {
            return (bool)$this->objectManager->get(self::IS_ENABLED)->isCaptchaEnabledFor(Config::RECAPTCHA_KEY);
        } catch (\Throwable $e) {
            $this->logger->warning('CommerceGears_Blog reCAPTCHA check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @throws LocalizedException when reCAPTCHA is enabled and the response is missing or invalid
     */
    public function validate(RequestInterface $request): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        try {
            $response = (string)$this->objectManager->get(self::RESPONSE_RESOLVER)->resolve($request);
            $validationConfig = $this->objectManager->get(self::CONFIG_RESOLVER)->get(Config::RECAPTCHA_KEY);
            $result = $this->objectManager->get(self::VALIDATOR)->isValid($response, $validationConfig);
        } catch (\Throwable) {
            throw new LocalizedException(__('Please complete the reCAPTCHA check and try again.'));
        }
        if (!$result->isValid()) {
            throw new LocalizedException(__('The reCAPTCHA check failed. Please try again.'));
        }
    }
}
