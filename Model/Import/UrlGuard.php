<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Import;

/**
 * Decides whether the importer may fetch a URL (protection against server-side request forgery).
 *
 * Only http and https on the default ports, no user names in the URL, and the host must resolve exclusively to
 * public IP addresses. The resolved address is returned so that the download connects to exactly that address
 * (no second DNS lookup that could answer differently).
 */
class UrlGuard
{
    /** @var callable(string): string[] */
    private $resolver;

    /**
     * @param callable(string): string[]|null $resolver host name => IP addresses (for tests)
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver ?? [$this, 'resolveDns'];
    }

    /**
     * @return array{scheme: string, host: string, port: int, ip: string}
     * @throws \InvalidArgumentException with the reason when the URL may not be fetched
     */
    public function check(string $url): array
    {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new \InvalidArgumentException('invalid URL');
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('invalid URL');
        }
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('only http and https are allowed');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('user names in URLs are not allowed');
        }
        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, [80, 443], true)) {
            throw new \InvalidArgumentException('only the standard ports 80 and 443 are allowed');
        }
        $host = strtolower(trim($parts['host'], '[]'));
        $host = rtrim($host, '.');
        if ($host === '' || $host === 'localhost' || preg_match('/\.(localhost|local|internal|localdomain|home\.arpa)$/', $host)) {
            throw new \InvalidArgumentException('local host names are not allowed');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver)($host);
        if (!$ips) {
            throw new \InvalidArgumentException('the host name could not be resolved');
        }
        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                throw new \InvalidArgumentException('the host resolves to a private or reserved address');
            }
        }
        return ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    public function isAllowed(string $url): bool
    {
        try {
            $this->check($url);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * True only for globally routable unicast addresses.
     */
    public function isPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->isPublicIpv4($ip);
        }
        $packed = inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return false;
        }
        // IPv4-mapped (::ffff:a.b.c.d), IPv4-compatible (::a.b.c.d) and NAT64 (64:ff9b::a.b.c.d): judge the IPv4 part
        $prefix12 = substr($packed, 0, 12);
        if ($prefix12 === str_repeat("\0", 10) . "\xff\xff" || $prefix12 === str_repeat("\0", 12)
            || $prefix12 === "\x00\x64\xff\x9b" . str_repeat("\0", 8)
        ) {
            $v4 = inet_ntop(substr($packed, 12));
            return $v4 !== false && $this->isPublicIpv4($v4);
        }
        foreach ([
            ['::', 128], ['::1', 128], ['fc00::', 7], ['fe80::', 10], ['fec0::', 10], ['ff00::', 8],
            ['2001:db8::', 32], ['2001::', 32], ['2002::', 16], ['100::', 64],
        ] as [$network, $bits]) {
            if ($this->inRange($packed, (string)inet_pton($network), $bits)) {
                return false;
            }
        }
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function isPublicIpv4(string $ip): bool
    {
        $packed = (string)inet_pton($ip);
        foreach ([
            ['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8], ['169.254.0.0', 16],
            ['172.16.0.0', 12], ['192.0.0.0', 24], ['192.0.2.0', 24], ['192.88.99.0', 24], ['192.168.0.0', 16],
            ['198.18.0.0', 15], ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4],
        ] as [$network, $bits]) {
            if ($this->inRange($packed, (string)inet_pton($network), $bits)) {
                return false;
            }
        }
        return true;
    }

    private function inRange(string $address, string $network, int $bits): bool
    {
        if (strlen($address) !== strlen($network)) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        if (substr($address, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xff << (8 - $rest)) & 0xff;
        return (ord($address[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }

    /**
     * @return string[] IPv4 and IPv6 addresses of the host
     */
    private function resolveDns(string $host): array
    {
        $ips = @gethostbynamel($host) ?: [];
        $records = @dns_get_record($host, DNS_AAAA) ?: [];
        foreach ($records as $record) {
            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
        return array_values(array_unique($ips));
    }
}
