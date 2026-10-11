<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Frontend;

use Magento\Framework\View\DesignInterface;

/**
 * Whether the current storefront theme is a Hyvä theme (itself or a parent is Hyva/*).
 */
class ThemeResolver
{
    private ?bool $isHyva = null;

    public function __construct(private readonly DesignInterface $design)
    {
    }

    public function isHyva(): bool
    {
        if ($this->isHyva === null) {
            $this->isHyva = false;
            $theme = $this->design->getDesignTheme();
            while ($theme) {
                if (str_starts_with((string)$theme->getCode(), 'Hyva/')) {
                    $this->isHyva = true;
                    break;
                }
                $theme = $theme->getParentTheme();
            }
        }
        return $this->isHyva;
    }
}
