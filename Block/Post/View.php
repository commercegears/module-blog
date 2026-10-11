<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block\Post;

use Magento\Framework\DataObject\IdentityInterface;
use CommerceGears\Blog\Block\AbstractBlog;
use CommerceGears\Blog\Model\Author;
use CommerceGears\Blog\Model\Post;
use CommerceGears\Blog\Model\Tag;

/**
 * The post on its own page, and the blocks around it (share links, author box, previous and next).
 */
class View extends AbstractBlog implements IdentityInterface
{
    public function getPost(): ?Post
    {
        return $this->currentPage->getPost();
    }

    public function isPreview(): bool
    {
        return $this->currentPage->isPreview();
    }

    public function isScheduled(): bool
    {
        return (bool)$this->getPost()?->isScheduled();
    }

    public function getContentHtml(): string
    {
        return $this->contentRenderer->getContentHtml($this->getPost(), $this->getStoreId());
    }

    /**
     * @return Tag[]
     */
    public function getTags(): array
    {
        return array_values($this->catalog->getTags($this->getPost()->getTagIds(), $this->getStoreId()));
    }

    public function getPostAuthor(): ?Author
    {
        return $this->getAuthor($this->getPost());
    }

    public function showAuthor(): bool
    {
        return $this->config->getFlag('post/show_author', $this->getStoreId());
    }

    /**
     * @return array<int, array{label: string, url: string, network: string}>
     */
    public function getShareLinks(): array
    {
        if (!$this->config->getFlag('post/show_share', $this->getStoreId()) || $this->isPreview()) {
            return [];
        }
        $url = rawurlencode($this->getPostUrl($this->getPost()));
        $title = rawurlencode($this->getPost()->getTitle());
        return [
            ['network' => 'linkedin', 'label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url],
            ['network' => 'x', 'label' => 'X', 'url' => 'https://x.com/intent/post?url=' . $url . '&text=' . $title],
            ['network' => 'facebook', 'label' => 'Facebook', 'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url],
            ['network' => 'email', 'label' => (string)__('E-mail'), 'url' => 'mailto:?subject=' . $title . '&body=' . $url],
        ];
    }

    /**
     * @return array{prev: ?Post, next: ?Post}
     */
    public function getNeighbours(): array
    {
        if (!$this->config->getFlag('post/show_prev_next', $this->getStoreId()) || $this->isPreview()) {
            return ['prev' => null, 'next' => null];
        }
        return $this->catalog->getNeighbours($this->getPost(), $this->getStoreId());
    }

    /**
     * @return array<string, string> network => profile URL
     */
    public function getAuthorLinks(Author $author): array
    {
        $links = [];
        foreach (['website_url' => (string)__('Website'), 'linkedin_url' => 'LinkedIn', 'x_url' => 'X'] as $field => $label) {
            $value = (string)$author->getData($field);
            if ($value !== '') {
                $links[$label] = $value;
            }
        }
        return $links;
    }

    public function getIdentities()
    {
        $post = $this->getPost();
        if (!$post) {
            return [];
        }
        $tags = [Post::CACHE_TAG, Post::CACHE_TAG . '_' . $post->getId(), Tag::CACHE_TAG];
        if ($post->getAuthorId()) {
            $tags[] = Author::CACHE_TAG . '_' . $post->getAuthorId();
        }
        return $tags;
    }
}
