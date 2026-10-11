<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Test\Unit\Model\Import;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Softaware\Blog\Model\Import\UrlGuard;

class UrlGuardTest extends TestCase
{
    private function guard(array $dns = []): UrlGuard
    {
        return new UrlGuard(static fn (string $host): array => $dns[$host] ?? []);
    }

    public function testPublicHostIsAllowedAndPinned(): void
    {
        $result = $this->guard(['images.example.org' => ['93.184.216.34']])->check('https://images.example.org/a.jpg');
        self::assertSame(['scheme' => 'https', 'host' => 'images.example.org', 'port' => 443, 'ip' => '93.184.216.34'], $result);
    }

    public static function blockedUrls(): array
    {
        return [
            'file scheme' => ['file:///etc/passwd'],
            'ftp' => ['ftp://example.org/a.jpg'],
            'gopher' => ['gopher://example.org/'],
            'no host' => ['https:///a.jpg'],
            'user info' => ['https://user:pass@public.example/a.jpg'],
            'other port' => ['http://public.example:8080/a.jpg'],
            'localhost' => ['http://localhost/a.jpg'],
            'local suffix' => ['http://printer.local/a.jpg'],
            'loopback ip' => ['http://127.0.0.1/a.jpg'],
            'private 10' => ['http://10.1.2.3/a.jpg'],
            'private 192' => ['http://192.168.1.10/a.jpg'],
            'private 172' => ['http://172.20.0.1/a.jpg'],
            'link local metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'cgnat' => ['http://100.64.0.1/a.jpg'],
            'zero' => ['http://0.0.0.0/a.jpg'],
            'ipv6 loopback' => ['http://[::1]/a.jpg'],
            'ipv6 unique local' => ['http://[fd00::1]/a.jpg'],
            'ipv6 link local' => ['http://[fe80::1]/a.jpg'],
            'ipv4 mapped loopback' => ['http://[::ffff:127.0.0.1]/a.jpg'],
            'dns to private' => ['https://internal.example.org/a.jpg'],
            'dns mixed public and private' => ['https://mixed.example.org/a.jpg'],
            'unresolvable' => ['https://nothing.example.org/a.jpg'],
            'control characters' => ["https://public.example/a\n.jpg"],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function testBlocked(string $url): void
    {
        $guard = $this->guard([
            'public.example' => ['93.184.216.34'],
            'internal.example.org' => ['10.0.0.5'],
            'mixed.example.org' => ['93.184.216.34', '127.0.0.1'],
        ]);
        self::assertFalse($guard->isAllowed($url));
    }

    public function testPublicIpChecks(): void
    {
        $guard = $this->guard();
        self::assertTrue($guard->isPublicIp('8.8.8.8'));
        self::assertTrue($guard->isPublicIp('2606:4700:4700::1111'));
        self::assertFalse($guard->isPublicIp('64:ff9b::7f00:1'));
        self::assertFalse($guard->isPublicIp('198.18.0.1'));
        self::assertFalse($guard->isPublicIp('224.0.0.1'));
        self::assertFalse($guard->isPublicIp('not-an-ip'));
    }
}
