<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Test\Unit\Model\Import\WordPress;

use PHPUnit\Framework\TestCase;
use CommerceGears\Blog\Model\Import\WordPress\WxrReader;

class WxrReaderTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../_files/wordpress-export.xml';

    public function testScanReadsSiteTermsAndAttachments(): void
    {
        $scan = (new WxrReader())->scan(self::FILE);
        self::assertSame('Test WordPress Blog', $scan['site']['title']);
        self::assertSame('https://wikimedia.org', $scan['site']['base_site_url']);
        self::assertSame(4, $scan['posts']);
        self::assertSame(6, $scan['items']);
        self::assertSame('testjane', $scan['authors'][0]['login']);
        self::assertSame('Test Jane Writer', $scan['authors'][0]['display_name']);
        self::assertCount(3, $scan['categories']);
        self::assertSame('test-wp-news', $scan['categories'][1]['parent']);
        self::assertSame('test-wp-tag', $scan['tags'][0]['slug']);
        self::assertSame(
            ['url' => 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg', 'alt' => 'Test example picture'],
            $scan['attachments'][50]
        );
    }

    public function testItemsAreParsedAndCanResumeFromAPosition(): void
    {
        $reader = new WxrReader();
        $items = iterator_to_array($reader->items(self::FILE));
        self::assertCount(6, $items);
        $post = $items[1];
        self::assertSame(10, $post['post_id']);
        self::assertSame('post', $post['post_type']);
        self::assertSame('Test WordPress post one &amp; more', $post['title']);
        self::assertSame('https://wikimedia.org/2024/05/test-wordpress-post-one/', $post['link']);
        self::assertStringContainsString('<!-- wp:paragraph -->', $post['content']);
        self::assertSame('50', $post['meta']['_thumbnail_id']);
        self::assertCount(3, $post['terms']);
        self::assertSame(['domain' => 'post_tag', 'slug' => 'test-wp-tag', 'name' => 'Test WP Tag'], $post['terms'][2]);
        self::assertCount(4, $post['comments']);
        self::assertSame(100, $post['comments'][1]['parent']);

        $resumed = iterator_to_array($reader->items(self::FILE, 4));
        self::assertSame([4, 5], array_keys($resumed));
        self::assertSame(13, $resumed[4]['post_id']);
    }

    public function testRejectsFilesThatAreNotWordPressExports(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wxr');
        file_put_contents($file, '<?xml version="1.0"?><rss><channel><title>x</title><item><title>y</title></item></channel></rss>');
        try {
            $this->expectException(\RuntimeException::class);
            (new WxrReader())->scan($file);
        } finally {
            unlink($file);
        }
    }

    public function testDoesNotExpandExternalEntities(): void
    {
        $secret = tempnam(sys_get_temp_dir(), 'secret');
        file_put_contents($secret, 'TOP-SECRET');
        $file = tempnam(sys_get_temp_dir(), 'wxr');
        file_put_contents($file, '<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY xxe SYSTEM "file://' . $secret . '">]>'
            . '<rss xmlns:wp="http://wordpress.org/export/1.2/"><channel><wp:base_site_url>https://a.example</wp:base_site_url>'
            . '<item><title>&xxe;</title><wp:post_type>post</wp:post_type></item></channel></rss>');
        try {
            foreach ((new WxrReader())->items($file) as $item) {
                self::assertStringNotContainsString('TOP-SECRET', $item['title']);
            }
        } catch (\RuntimeException) {
            // an XML error is fine as well: the file content was not read
            self::assertTrue(true);
        } finally {
            unlink($file);
            unlink($secret);
        }
    }
}
