<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Blog path and URL prefixes: lower case letters, digits and hyphens; the blog path may have several levels.
 */
class UrlPart extends Value
{
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
        $this->setValue($value);
        return parent::beforeSave();
    }
}
