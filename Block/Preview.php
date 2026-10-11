<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block;

use Magento\Framework\View\Element\AbstractBlock;

/**
 * Empty block declared cacheable="false" in the preview handle, so preview pages are never cached.
 */
class Preview extends AbstractBlock
{
    protected function _toHtml()
    {
        return '';
    }
}
