<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Test\Unit\Model\Import\WordPress;

use PHPUnit\Framework\TestCase;
use Softaware\Blog\Model\Import\WordPress\ContentConverter;

class ContentConverterTest extends TestCase
{
    private ContentConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ContentConverter();
    }

    public function testBlockEditorCommentsAreRemoved(): void
    {
        $html = "<!-- wp:paragraph -->\n<p>One</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:image {\"id\":5} -->\n<figure><img src=\"a.jpg\" alt=\"\"></figure>\n<!-- /wp:image -->";
        self::assertSame("<p>One</p>\n\n<figure><img src=\"a.jpg\" alt=\"\"></figure>", $this->converter->convert($html));
    }

    public function testClassicContentGetsParagraphs(): void
    {
        $html = $this->converter->convert("First line\nsecond line\n\n<h2>Title</h2>\nNext paragraph");
        self::assertSame("<p>First line<br>\nsecond line</p>\n<h2>Title</h2>\n<p>Next paragraph</p>", $html);
    }

    public function testCaptionsBecomeFigures(): void
    {
        $html = $this->converter->convert('[caption id="attachment_5" align="alignnone" width="300"]<img src="https://e.org/a.jpg" alt="" /> A caption[/caption]');
        self::assertSame(
            '<figure class="wp-caption"><img src="https://e.org/a.jpg" alt="" /><figcaption>A caption</figcaption></figure>',
            str_replace("\n", '', $html)
        );
    }

    public function testEmbedsBecomeLinks(): void
    {
        self::assertSame(
            '<p><a href="https://video.example/watch?v=1&amp;t=2">https://video.example/watch?v=1&amp;t=2</a></p>',
            $this->converter->convert('[embed]https://video.example/watch?v=1&t=2[/embed]')
        );
    }

    public function testImagesAreFoundAndReplaced(): void
    {
        $html = '<a href="https://e.org/big.jpg"><img class="x" src="https://e.org/small.jpg" srcset="https://e.org/small.jpg 1x, https://e.org/big.jpg 2x" sizes="100vw" alt="A"></a>'
            . '<a href="https://e.org/page/">Page</a><img src="data:image/png;base64,xx">';
        self::assertSame(['https://e.org/small.jpg', 'https://e.org/big.jpg'], $this->converter->findImageUrls($html));
        $replaced = $this->converter->replaceImageUrls($html, [
            'https://e.org/small.jpg' => '{{media url="softaware_blog/post/small.jpg"}}',
            'https://e.org/big.jpg' => '{{media url="softaware_blog/post/big.jpg"}}',
        ]);
        self::assertSame(
            '<a href="{{media url="softaware_blog/post/big.jpg"}}"><img class="x" src="{{media url="softaware_blog/post/small.jpg"}}" alt="A"></a>'
                . '<a href="https://e.org/page/">Page</a><img src="data:image/png;base64,xx">',
            $replaced
        );
    }

    public function testShortcodesAreReported(): void
    {
        self::assertSame(['gallery', 'contact-form-7'], $this->converter->findShortcodes('[gallery ids="1,2"] text [contact-form-7 id="3"]'));
    }

    public function testTemplateDirectivesStayText(): void
    {
        $html = $this->converter->convert('<p>Use {{block class="Magento\\Cms\\Block\\Block" block_id="x"}} or {{ name }}</p>');
        self::assertStringNotContainsString('{{', $html);
        self::assertStringContainsString('&#123;&#123;block class=', $html);
    }
}
