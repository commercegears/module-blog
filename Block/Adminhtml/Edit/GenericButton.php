<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block\Adminhtml\Edit;

use Magento\Backend\Block\Widget\Context;

/**
 * Shared by the edit form buttons of posts, categories, tags, authors and comments (the entity follows from the
 * controller name of the request).
 */
class GenericButton
{
    /** controller => [entity label, delete ACL resource] */
    private const ENTITIES = [
        'post' => ['post', 'CommerceGears_Blog::posts_delete'],
        'category' => ['category', 'CommerceGears_Blog::categories'],
        'tag' => ['tag', 'CommerceGears_Blog::tags'],
        'author' => ['author', 'CommerceGears_Blog::authors'],
        'comment' => ['comment', 'CommerceGears_Blog::comments_delete'],
    ];

    public function __construct(protected readonly Context $context)
    {
    }

    public function getId(): int
    {
        return (int)$this->context->getRequest()->getParam('id');
    }

    public function getStoreId(): int
    {
        return (int)$this->context->getRequest()->getParam('store');
    }

    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }

    protected function getEntity(): string
    {
        $controller = (string)$this->context->getRequest()->getControllerName();
        return isset(self::ENTITIES[$controller]) ? $controller : 'post';
    }

    protected function getEntityLabel(): string
    {
        return (string)__(self::ENTITIES[$this->getEntity()][0]);
    }

    protected function getDeleteResource(): string
    {
        return self::ENTITIES[$this->getEntity()][1];
    }
}
