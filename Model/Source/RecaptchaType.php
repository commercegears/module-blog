<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Same values as Magento's reCAPTCHA type sources, so Magento_ReCaptchaUi reads the setting for the
 * "softaware_blog_comment" form key.
 */
class RecaptchaType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('No')],
            ['value' => 'recaptcha', 'label' => __('reCAPTCHA v2 ("I am not a robot")')],
            ['value' => 'invisible', 'label' => __('reCAPTCHA v2 Invisible')],
            ['value' => 'recaptcha_v3', 'label' => __('reCAPTCHA v3 Invisible')],
        ];
    }
}
