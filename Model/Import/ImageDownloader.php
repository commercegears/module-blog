<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Import;

/**
 * Downloads one image for the WordPress import.
 *
 * Every request (and every redirect hop) is checked by UrlGuard and pinned to the checked IP address; only http
 * and https, at most 3 redirects, 5 s to connect, 20 s in total, 10 MB, and the response must be an image.
 */
class ImageDownloader
{
    public const MAX_BYTES = 10485760;
    private const MAX_REDIRECTS = 3;
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT = 20;

    public function __construct(private readonly UrlGuard $urlGuard)
    {
    }

    /**
     * @return string the image bytes
     * @throws \RuntimeException with a short reason
     */
    public function download(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('the PHP curl extension is missing');
        }
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            try {
                $target = $this->urlGuard->check($url);
            } catch (\InvalidArgumentException $e) {
                throw new \RuntimeException($e->getMessage(), 0, $e);
            }
            [$status, $headers, $body] = $this->request($url, $target);
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $location = $headers['location'] ?? '';
                if ($location === '') {
                    throw new \RuntimeException('redirect without a location');
                }
                $url = $this->resolveLocation($url, $location);
                continue;
            }
            if ($status !== 200) {
                throw new \RuntimeException('HTTP status ' . $status);
            }
            $type = strtolower(trim(explode(';', $headers['content-type'] ?? '')[0]));
            if ($type !== '' && !str_starts_with($type, 'image/') && $type !== 'application/octet-stream') {
                throw new \RuntimeException('not an image (' . $type . ')');
            }
            if ($body === '') {
                throw new \RuntimeException('empty response');
            }
            return $body;
        }
        throw new \RuntimeException('too many redirects');
    }

    /**
     * Absolute URL of a redirect target.
     */
    public function resolveLocation(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($location, '//')) {
            return ($parts['scheme'] ?? 'https') . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $path = (string)($parts['path'] ?? '/');
        return $origin . substr($path, 0, (int)strrpos($path, '/') + 1) . $location;
    }

    /**
     * @param array{scheme: string, host: string, port: int, ip: string} $target
     * @return array{0: int, 1: array<string, string>, 2: string}
     */
    private function request(string $url, array $target): array
    {
        $body = '';
        $headers = [];
        $tooLarge = false;
        $ip = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];
        $curl = curl_init($url);
        $options = [
            CURLOPT_RESOLVE => [$target['host'] . ':' . $target['port'] . ':' . $ip],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_PROXY => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; CommerceGearsBlogImport/1.0)',
            CURLOPT_HTTPHEADER => ['Accept: image/*'],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $pos = strpos($line, ':');
                if ($pos !== false) {
                    $headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ];
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'http,https';
            $options[CURLOPT_REDIR_PROTOCOLS_STR] = 'http,https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        curl_setopt_array($curl, $options);
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        unset($curl);
        if ($tooLarge) {
            throw new \RuntimeException('larger than 10 MB');
        }
        if ($ok === false) {
            throw new \RuntimeException($error !== '' ? $error : 'download failed');
        }
        return [$status, $headers, $body];
    }
}
