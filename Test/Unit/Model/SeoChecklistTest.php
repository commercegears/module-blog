<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Softaware\Blog\Model\SeoChecklist;
use Softaware\Blog\Model\TextAnalyser;

class SeoChecklistTest extends TestCase
{
    private SeoChecklist $checklist;

    protected function setUp(): void
    {
        $this->checklist = new SeoChecklist(new TextAnalyser());
    }

    /**
     * @param array<int, array{id: string, status: string}> $checks
     * @return array<string, string>
     */
    private function statuses(array $checks): array
    {
        return array_column($checks, 'status', 'id');
    }

    public function testGoodPostScoresFull(): void
    {
        $words = str_repeat('word ', 320);
        $result = $this->checklist->analyse([
            'title' => 'How to choose a yoga mat for home practice',
            'meta_description' => str_pad('Choosing a yoga mat: thickness, grip and size explained so you can buy the right yoga mat for your practice at home', 130, '.'),
            'url_key' => 'how-to-choose-a-yoga-mat',
            'focus_keyword' => 'Yoga mat',
            'content' => '<p>A yoga mat matters. ' . $words . '</p><h2>Grip</h2><p><a href="/gear.html">Gear</a> <img src="a.jpg" alt="Mat"></p>',
            'featured_image' => 'post/a.jpg',
            'featured_image_alt' => 'A mat',
        ], ['shop.example']);
        $statuses = $this->statuses($result['checks']);
        self::assertSame(100, $result['score']);
        self::assertSame('good', $statuses['keyword_url']);
        self::assertSame('info', $statuses['reading_time']);
    }

    public function testProblemsAreReported(): void
    {
        $result = $this->checklist->analyse([
            'title' => 'Hi',
            'content' => '<h1>Big</h1><p>Short text <a href="https://other.example/">x</a></p><img src="a.jpg">',
        ], ['shop.example']);
        $statuses = $this->statuses($result['checks']);
        self::assertSame('warning', $statuses['title']);
        self::assertSame('warning', $statuses['description']);
        self::assertSame('warning', $statuses['keyword']);
        self::assertSame('problem', $statuses['length']);
        self::assertSame('problem', $statuses['headings']);
        self::assertSame('warning', $statuses['links']);
        self::assertSame('problem', $statuses['images']);
        self::assertLessThan(50, $result['score']);
    }

    public function testInternalLinksIncludeOwnHostsAndDirectives(): void
    {
        $html = '<a href="https://www.shop.example/a">a</a><a href="{{store url=""}}">b</a><a href="#top">c</a>'
            . '<a href="mailto:x@y.z">d</a><a href="//cdn.other/x">e</a><a href="relative/page">f</a>';
        self::assertSame(3, $this->checklist->countInternalLinks($html, ['shop.example']));
    }

    public function testImagesWithoutAltText(): void
    {
        self::assertSame([3, 2], $this->checklist->countImages('<img src="a" alt="Fine"><img src="b" alt=""><img src="c">'));
    }
}
