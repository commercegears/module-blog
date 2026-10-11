<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model;

/**
 * The blog page being rendered in this request (set by the storefront controllers, read by blocks).
 */
class CurrentPage
{
    public const TYPE_HOME = 'home';
    public const TYPE_POST = 'post';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_TAG = 'tag';
    public const TYPE_AUTHOR = 'author';

    private ?string $type = null;
    private ?Post $post = null;
    private ?Category $category = null;
    private ?Tag $tag = null;
    private ?Author $author = null;
    private int $pageNumber = 1;
    private bool $preview = false;

    public function setHome(int $pageNumber): void
    {
        $this->type = self::TYPE_HOME;
        $this->pageNumber = max(1, $pageNumber);
    }

    public function setPost(Post $post, bool $preview = false): void
    {
        $this->type = self::TYPE_POST;
        $this->post = $post;
        $this->preview = $preview;
    }

    public function setCategory(Category $category, int $pageNumber): void
    {
        $this->type = self::TYPE_CATEGORY;
        $this->category = $category;
        $this->pageNumber = max(1, $pageNumber);
    }

    public function setTag(Tag $tag, int $pageNumber): void
    {
        $this->type = self::TYPE_TAG;
        $this->tag = $tag;
        $this->pageNumber = max(1, $pageNumber);
    }

    public function setAuthor(Author $author, int $pageNumber): void
    {
        $this->type = self::TYPE_AUTHOR;
        $this->author = $author;
        $this->pageNumber = max(1, $pageNumber);
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function isBlogPage(): bool
    {
        return $this->type !== null;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function getTag(): ?Tag
    {
        return $this->tag;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    public function isPreview(): bool
    {
        return $this->preview;
    }
}
