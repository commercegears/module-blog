<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block\Widget;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Widget\Block\BlockInterface;
use CommerceGears\Blog\Block\AbstractBlog;
use CommerceGears\Blog\Model\Category;
use CommerceGears\Blog\Model\Post;
use CommerceGears\Blog\Model\Tag;

/**
 * CMS widgets (Content > Widgets, or {{widget}} in pages and blocks). Hyvä themes get their own templates.
 */
abstract class AbstractWidget extends AbstractBlog implements BlockInterface, IdentityInterface
{
    /** Luma template => Hyvä template */
    protected const TEMPLATES = [];

    protected function _construct()
    {
        parent::_construct();
        $this->addData(['cache_lifetime' => 86400]);
    }

    public function getTemplate()
    {
        $template = (string)$this->getData('template');
        if ($template === '') {
            $template = (string)array_key_first(static::TEMPLATES);
        }
        if ($this->isHyva() && isset(static::TEMPLATES[$template])) {
            return static::TEMPLATES[$template];
        }
        return $template;
    }

    public function getCacheKeyInfo()
    {
        return array_merge(parent::getCacheKeyInfo(), [
            static::class,
            (int)$this->_storeManager->getStore()->getId(),
            $this->isHyva() ? 'hyva' : 'luma',
            (string)json_encode($this->getData()),
        ]);
    }

    public function getTitle(): string
    {
        return trim((string)$this->getData('title'));
    }

    protected function _toHtml()
    {
        return $this->isEnabled() ? parent::_toHtml() : '';
    }

    public function getIdentities()
    {
        return [Post::CACHE_TAG, Category::CACHE_TAG, Tag::CACHE_TAG];
    }
}
