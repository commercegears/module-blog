<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Frontend;

use Softaware\Blog\Model\Config;
use Softaware\Blog\Model\CurrentPage;
use Softaware\Blog\Model\Url;

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
