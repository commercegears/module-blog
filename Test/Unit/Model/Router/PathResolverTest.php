<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Test\Unit\Model\Router;

use PHPUnit\Framework\TestCase;
use CommerceGears\Blog\Model\Router\PathResolver;

class PathResolverTest extends TestCase
{
    private const PREFIXES = ['category' => 'category', 'tag' => 'tag', 'author' => 'author'];

    public function testResolvesBlogAddresses(): void
    {
        $resolver = new PathResolver();
        self::assertSame('home', $resolver->resolve('blog', 'blog', '', self::PREFIXES)['type']);
        $post = $resolver->resolve('blog/my-post', 'blog', '', self::PREFIXES);
        self::assertSame(['post', 'my-post', 'blog/my-post'], [$post['type'], $post['key'], $post['canonical']]);
        $category = $resolver->resolve('blog/category/running.html', 'blog', '.html', self::PREFIXES);
        self::assertSame(['category', 'running', 'blog/category/running.html'], [$category['type'], $category['key'], $category['canonical']]);
        self::assertSame('rss', $resolver->resolve('blog/rss.xml', 'blog', '', self::PREFIXES)['type']);
        self::assertSame('news/my-post/', $resolver->resolve('news/my-post', 'news', '/', self::PREFIXES)['canonical']);
    }

    public function testIgnoresOtherAddresses(): void
    {
        $resolver = new PathResolver();
        self::assertNull($resolver->resolve('blogger/post', 'blog', '', self::PREFIXES));
        self::assertNull($resolver->resolve('blog/unknown/thing', 'blog', '', self::PREFIXES));
        self::assertNull($resolver->resolve('blog/a/b/c', 'blog', '', self::PREFIXES));
        self::assertNull($resolver->resolve('blog/<script>', 'blog', '', self::PREFIXES));
    }
}
