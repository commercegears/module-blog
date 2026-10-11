<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Frontend;

use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\CurrentPage;
use CommerceGears\Blog\Model\Url;

/**
 * Dependencies shared by every storefront blog block (keeps block constructors short).
 */
class BlockContext
{
    public function __construct(
        public readonly Config $config,
        public readonly Catalog $catalog,
        public readonly Url $url,
        public readonly ContentRenderer $contentRenderer,
        public readonly CurrentPage $currentPage,
        public readonly ThemeResolver $themeResolver
    ) {
    }
}
