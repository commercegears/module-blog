<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Plugin;

use Magento\Framework\Data\Tree\Node;
use Softaware\Blog\Model\Frontend\MenuLink;

/**
 * Hyvä navigation (Hyva\Theme\Service\Navigation::getMenuTree): adds the blog link. The subject is not type-hinted,
 * so nothing here depends on Hyvä classes; without Hyvä the plugin is simply never called.
 */
class HyvaNavigationLink
{
    public function __construct(private readonly MenuLink $menuLink)
    {
    }

    /**
     * @param object $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetMenuTree($subject, $result)
    {
        if ($result instanceof Node && !$result->getData('softaware_blog_link')) {
            // the tree object is reused within a request: add the link once
            $result->setData('softaware_blog_link', true);
            $this->menuLink->addTo($result, false);
        }
        return $result;
    }
}
