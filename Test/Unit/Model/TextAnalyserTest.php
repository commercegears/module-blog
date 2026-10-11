<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use CommerceGears\Blog\Model\TextAnalyser;

class TextAnalyserTest extends TestCase
{
    public function testPlainTextWordsAndReadingTime(): void
    {
        $analyser = new TextAnalyser();
        $text = $analyser->toPlainText('<p>One <b>two</b></p><script>bad()</script><p>three {{widget type="x"}} four&amp;five</p>');
        self::assertSame('One two three four&five', $text);
        self::assertSame(4, $analyser->wordCount($text));
        self::assertSame(2, $analyser->readingMinutes(201, 200));
        self::assertSame(0, $analyser->readingMinutes(0, 200));
        self::assertSame('Hello wonderful…', $analyser->truncate('Hello wonderful world', 18));
        self::assertSame('Short', $analyser->truncate('Short', 18));
    }
}
