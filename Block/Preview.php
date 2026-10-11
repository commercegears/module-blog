<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block;

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
