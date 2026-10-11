<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Controller\Index;

use Magento\Framework\Controller\ResultInterface;
use Softaware\Blog\Controller\AbstractView;

/**
 * Blog home page: latest posts.
 */
class Index extends AbstractView
{
    public function execute(): ResultInterface
    {
        $this->assertEnabled();
        $this->currentPage->setHome($this->getPageNumber());
        return $this->createPage();
    }
}
