<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Controller\Adminhtml\Post;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use CommerceGears\Blog\Model\ImageUploader;

/**
 * Image upload of the edit form (JPEG, PNG, GIF or WebP, checked as an image).
 */
class Upload extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_Blog::posts_save';

    public function __construct(Action\Context $context, private readonly ImageUploader $imageUploader)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $result = $this->imageUploader->upload((string)($this->getRequest()->getParam('param_name') ?: 'featured_image'), 'post');
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($result);
    }
}
