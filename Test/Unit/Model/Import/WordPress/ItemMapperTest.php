<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Test\Unit\Model\Import\WordPress;

use PHPUnit\Framework\TestCase;
use CommerceGears\Blog\Model\Import\WordPress\ItemMapper;

class ItemMapperTest extends TestCase
{
    private ItemMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ItemMapper();
    }

    public function testExternalIdsAreStablePerSite(): void
    {
        $key = $this->mapper->siteKey('https://www.example.com/blog/');
        self::assertSame($key, $this->mapper->siteKey('https://example.com/blog'));
        self::assertNotSame($key, $this->mapper->siteKey('https://other.example.com'));
        self::assertSame('wp:' . $key . ':post:10', $this->mapper->externalId($key, 'post', 10));
    }

    public function testStatusMapping(): void
    {
        $published = ['status' => 'publish', 'post_date_gmt' => '2024-05-01 09:00:00', 'post_date' => '2024-05-01 10:00:00'];
        self::assertSame(['status' => 1, 'publish_at' => '2024-05-01 09:00:00'], $this->mapper->mapStatus($published));
        self::assertSame(['status' => 0, 'publish_at' => '2024-05-01 09:00:00'], $this->mapper->mapStatus($published, true));
        self::assertSame(
            ['status' => 1, 'publish_at' => '2030-01-01 09:00:00'],
            $this->mapper->mapStatus(['status' => 'future', 'post_date_gmt' => '2030-01-01 09:00:00'])
        );
        self::assertSame(
            ['status' => 0, 'publish_at' => null],
            $this->mapper->mapStatus(['status' => 'draft', 'post_date_gmt' => '0000-00-00 00:00:00', 'post_date' => '2024-06-01 10:00:00'])
        );
        self::assertSame(0, $this->mapper->mapStatus($published + ['post_password' => 'secret'])['status']);
        self::assertSame(0, $this->mapper->mapStatus(['status' => 'private'])['status']);
    }

    public function testOnlyRealPostsAreImported(): void
    {
        self::assertTrue($this->mapper->isPost(['post_type' => 'post', 'status' => 'publish']));
        self::assertFalse($this->mapper->isPost(['post_type' => 'page', 'status' => 'publish']));
        self::assertFalse($this->mapper->isPost(['post_type' => 'post', 'status' => 'auto-draft']));
        self::assertFalse($this->mapper->isPost(['post_type' => 'attachment', 'status' => 'inherit']));
    }

    public function testSeoMetaFromPlugins(): void
    {
        $seo = $this->mapper->mapSeo([
            '_yoast_wpseo_title' => '%%title%% %%sep%% %%sitename%%',
            '_yoast_wpseo_metadesc' => 'Yoast description &amp; more',
            '_yoast_wpseo_focuskw' => 'yoga mat',
            '_yoast_wpseo_meta-robots-noindex' => '1',
        ]);
        self::assertSame('', $seo['meta_title']);
        self::assertSame('Yoast description & more', $seo['meta_description']);
        self::assertSame('yoga mat', $seo['focus_keyword']);
        self::assertSame('NOINDEX,FOLLOW', $seo['meta_robots']);

        $rankMath = $this->mapper->mapSeo(['rank_math_title' => 'Plain title', 'rank_math_focus_keyword' => 'first,second']);
        self::assertSame('Plain title', $rankMath['meta_title']);
        self::assertSame('first', $rankMath['focus_keyword']);
        self::assertSame('', $rankMath['meta_robots']);
    }

    public function testTermsSkipWordPressDefaultCategory(): void
    {
        $item = ['terms' => [
            ['domain' => 'category', 'slug' => 'uncategorized', 'name' => 'Uncategorized'],
            ['domain' => 'category', 'slug' => 'caf%c3%a9', 'name' => 'Caf&eacute;'],
            ['domain' => 'post_tag', 'slug' => 'tips', 'name' => 'Tips'],
        ]];
        self::assertSame([['slug' => 'café', 'name' => 'Café']], $this->mapper->terms($item, 'category'));
        self::assertSame([['slug' => 'tips', 'name' => 'Tips']], $this->mapper->terms($item, 'post_tag'));
    }

    public function testCommentMapping(): void
    {
        $approved = $this->mapper->mapComment([
            'approved' => '1', 'type' => 'comment', 'author' => 'Reader', 'author_email' => 'r@example.com',
            'content' => 'Nice <b>post</b> &amp; thanks', 'date_gmt' => '2024-05-02 09:00:00',
        ]);
        self::assertSame(1, $approved['status']);
        self::assertSame('Nice post & thanks', $approved['content']);
        self::assertSame('2024-05-02 09:00:00', $approved['created_at']);
        self::assertSame(0, $this->mapper->mapComment(['approved' => '0', 'content' => 'x', 'author' => 'a'])['status']);
        self::assertNull($this->mapper->mapComment(['approved' => '0', 'content' => 'x', 'author' => 'a', 'author_email' => 'invalid'])['author_email']);
        self::assertNull($this->mapper->mapComment(['approved' => 'spam', 'content' => 'x']));
        self::assertNull($this->mapper->mapComment(['approved' => 'trash', 'content' => 'x']));
        self::assertNull($this->mapper->mapComment(['approved' => '1', 'type' => 'pingback', 'content' => 'x']));
        self::assertNull($this->mapper->mapComment(['approved' => '1', 'type' => 'comment', 'content' => '  ']));
    }

    public function testOldPathForRedirects(): void
    {
        self::assertSame('2024/05/my-post/', $this->mapper->oldPath('https://example.com/2024/05/my-post/'));
        self::assertSame('my-post', $this->mapper->oldPath('https://example.com/my-post'));
        self::assertNull($this->mapper->oldPath('https://example.com/?p=12'));
        self::assertNull($this->mapper->oldPath('https://example.com/'));
        self::assertNull($this->mapper->oldPath('https://example.com/a/../b'));
    }

    public function testSiteImagesAndFileNames(): void
    {
        $hosts = $this->mapper->siteHosts(['base_site_url' => 'https://www.example.com', 'base_blog_url' => 'https://example.com/blog']);
        self::assertSame(['example.com'], $hosts);
        self::assertTrue($this->mapper->isSiteImage('https://example.com/wp-content/uploads/a.jpg', $hosts));
        self::assertTrue($this->mapper->isSiteImage('https://cdn.example.com/a.jpg', $hosts));
        self::assertTrue($this->mapper->isSiteImage('https://i0.wp.com/example.com/a.jpg', $hosts));
        self::assertFalse($this->mapper->isSiteImage('https://notexample.com/a.jpg', $hosts));
        self::assertFalse($this->mapper->isSiteImage('https://example.com.evil.org/a.jpg', $hosts));
        self::assertSame('photo.jpg', $this->mapper->imageFileName('https://example.com/wp-content/uploads/2024/05/photo-1024x768.jpg'));
        self::assertSame('my photo.png', $this->mapper->imageFileName('https://example.com/my%20photo.png?ver=2'));
    }
}
