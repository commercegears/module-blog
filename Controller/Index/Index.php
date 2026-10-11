<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Index;

use Magento\Framework\Controller\ResultInterface;
use CommerceGears\Blog\Controller\AbstractView;

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
