<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Test\Unit\Model\Comment;

use PHPUnit\Framework\TestCase;
use Softaware\Blog\Model\Comment\Guard;

class GuardTest extends TestCase
{
    public function testSpamRules(): void
    {
        $guard = new Guard();
        self::assertTrue($guard->isHoneypotFilled('http://spam'));
        self::assertFalse($guard->isHoneypotFilled(' '));
        self::assertTrue($guard->containsLink('visit www.cheap.example now'));
        self::assertTrue($guard->containsLink('see https://x.y'));
        self::assertFalse($guard->containsLink('Thanks, very helpful.'));
        self::assertNull($guard->hashIp('', 'salt'));
        self::assertSame(64, strlen((string)$guard->hashIp('203.0.113.5', 'salt')));
        self::assertNotNull($guard->validateText('short', 10, 100));
        self::assertNull($guard->validateText('long enough text', 10, 100));
        self::assertNotNull($guard->validateText(str_repeat('a', 101), 10, 100));
    }
}
