<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Router;

/**
 * Pure path parsing for the blog router (unit tested).
 */
class PathResolver
{
    /**
     * @param string $path request path without the leading slash
     * @param array<string, string> $prefixes type => URL prefix (category, tag, author)
     * @return array{type: string, key: string, controller: string, action: string, canonical: ?string}|null
     */
    public function resolve(string $path, string $route, string $suffix, array $prefixes): ?array
    {
        $route = trim($route, '/');
        if ($route === '' || ($path !== $route && !str_starts_with($path, $route . '/'))) {
            return null;
        }
        $rest = (string)substr($path, strlen($route) + 1);
        if ($path === $route || $rest === '') {
            return $this->result('home', '', 'index', 'index', $suffix === '/' ? $route . '/' : $route);
        }
        if ($rest === 'rss.xml') {
            return $this->result('rss', '', 'rss', 'index', null);
        }
        $key = $rest;
        if (str_ends_with($key, '/')) {
            $key = substr($key, 0, -1);
        } elseif (str_ends_with($key, '.html')) {
            $key = substr($key, 0, -5);
        }
        if ($key === '' || !preg_match('#^[a-z0-9][a-z0-9_.-]*(/[a-z0-9][a-z0-9_.-]*)?$#i', $key)) {
            return null;
        }
        $parts = explode('/', $key);
        if (count($parts) === 1) {
            return $this->result('post', $key, 'post', 'view', $route . '/' . $key . $suffix);
        }
        foreach ($prefixes as $type => $prefix) {
            if ($parts[0] === $prefix) {
                return $this->result($type, $parts[1], $type, 'view', $route . '/' . $prefix . '/' . $parts[1] . $suffix);
            }
        }
        return null;
    }

    /**
     * @return array{type: string, key: string, controller: string, action: string, canonical: ?string}
     */
    private function result(string $type, string $key, string $controller, string $action, ?string $canonical): array
    {
        return [
            'type' => $type,
            'key' => $key,
            'controller' => $controller,
            'action' => $action,
            'canonical' => $canonical,
        ];
    }
}
