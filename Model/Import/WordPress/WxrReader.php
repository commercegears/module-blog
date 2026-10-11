<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Import\WordPress;

/**
 * Streams a WordPress export file (WXR, Tools > Export in WordPress) with XMLReader, so files of any size are
 * read with little memory. No framework dependencies (unit tested).
 *
 * Elements are matched by their prefix as WordPress writes it (wp:, content:, excerpt:, dc:), which works for
 * every WXR version (1.0 to 1.2). External entities and network access are never loaded.
 */
class WxrReader
{
    private const LIBXML_OPTIONS = LIBXML_NONET | LIBXML_COMPACT;

    /**
     * Reads the site details, authors, categories and tags, and the attachments (for featured images).
     *
     * @return array{site: array<string, string>, authors: array<int, array<string, string>>,
     *     categories: array<int, array<string, string>>, tags: array<int, array<string, string>>,
     *     attachments: array<int, array{url: string, alt: string}>, items: int, posts: int}
     * @throws \RuntimeException when the file is not a readable WordPress export
     */
    public function scan(string $path): array
    {
        $result = [
            'site' => ['title' => '', 'link' => '', 'base_site_url' => '', 'base_blog_url' => '', 'language' => ''],
            'authors' => [],
            'categories' => [],
            'tags' => [],
            'attachments' => [],
            'items' => 0,
            'posts' => 0,
        ];
        $isWordPress = false;
        $this->walk($path, function (string $name, \DOMElement $node) use (&$result, &$isWordPress): void {
            switch ($name) {
                case 'title':
                case 'link':
                case 'language':
                    $result['site'][$name] = trim($node->textContent);
                    break;
                case 'wp:base_site_url':
                case 'wp:base_blog_url':
                    $result['site'][substr($name, 3)] = trim($node->textContent);
                    $isWordPress = true;
                    break;
                case 'wp:author':
                    $result['authors'][] = [
                        'id' => $this->child($node, 'wp', 'author_id'),
                        'login' => $this->child($node, 'wp', 'author_login'),
                        'email' => $this->child($node, 'wp', 'author_email'),
                        'display_name' => $this->child($node, 'wp', 'author_display_name'),
                        'first_name' => $this->child($node, 'wp', 'author_first_name'),
                        'last_name' => $this->child($node, 'wp', 'author_last_name'),
                    ];
                    break;
                case 'wp:category':
                    $result['categories'][] = [
                        'term_id' => $this->child($node, 'wp', 'term_id'),
                        'slug' => $this->child($node, 'wp', 'category_nicename'),
                        'parent' => $this->child($node, 'wp', 'category_parent'),
                        'name' => $this->child($node, 'wp', 'cat_name'),
                        'description' => $this->child($node, 'wp', 'category_description'),
                    ];
                    break;
                case 'wp:tag':
                    $result['tags'][] = [
                        'term_id' => $this->child($node, 'wp', 'term_id'),
                        'slug' => $this->child($node, 'wp', 'tag_slug'),
                        'name' => $this->child($node, 'wp', 'tag_name'),
                        'description' => $this->child($node, 'wp', 'tag_description'),
                    ];
                    break;
                case 'item':
                    $result['items']++;
                    $type = $this->child($node, 'wp', 'post_type');
                    if ($type !== '') {
                        $isWordPress = true;
                    }
                    if ($type === 'post') {
                        $result['posts']++;
                    } elseif ($type === 'attachment') {
                        $id = (int)$this->child($node, 'wp', 'post_id');
                        $url = $this->child($node, 'wp', 'attachment_url') ?: $this->child($node, '', 'guid');
                        if ($id > 0 && $url !== '') {
                            $meta = $this->meta($node);
                            $result['attachments'][$id] = [
                                'url' => $url,
                                'alt' => trim((string)($meta['_wp_attachment_image_alt'] ?? '')),
                            ];
                        }
                    }
                    break;
            }
        });
        if (!$isWordPress) {
            throw new \RuntimeException('The file is not a WordPress export (WXR) file.');
        }
        return $result;
    }

    /**
     * Items of the file from position $offset (0 = first item), as plain arrays.
     *
     * @return \Generator<int, array<string, mixed>> position => item
     */
    public function items(string $path, int $offset = 0): \Generator
    {
        $position = -1;
        $reader = $this->open($path);
        try {
            $ok = $reader->read();
            while ($ok) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->depth === 2 && $reader->name === 'item') {
                    $position++;
                    if ($position >= $offset) {
                        $node = $reader->expand();
                        if ($node instanceof \DOMElement) {
                            yield $position => $this->parseItem($node);
                        }
                    }
                    $ok = $reader->next();
                    continue;
                }
                $ok = $reader->read();
            }
            $this->assertNoError();
        } finally {
            $reader->close();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function parseItem(\DOMElement $node): array
    {
        $terms = [];
        $comments = [];
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->nodeName === 'category') {
                $terms[] = [
                    'domain' => $child->getAttribute('domain'),
                    'slug' => $child->getAttribute('nicename'),
                    'name' => trim($child->textContent),
                ];
            } elseif ($child->nodeName === 'wp:comment') {
                $comments[] = [
                    'id' => (int)$this->child($child, 'wp', 'comment_id'),
                    'author' => $this->child($child, 'wp', 'comment_author'),
                    'author_email' => $this->child($child, 'wp', 'comment_author_email'),
                    'author_url' => $this->child($child, 'wp', 'comment_author_url'),
                    'date' => $this->child($child, 'wp', 'comment_date'),
                    'date_gmt' => $this->child($child, 'wp', 'comment_date_gmt'),
                    'content' => $this->child($child, 'wp', 'comment_content', false),
                    'approved' => $this->child($child, 'wp', 'comment_approved'),
                    'type' => $this->child($child, 'wp', 'comment_type'),
                    'parent' => (int)$this->child($child, 'wp', 'comment_parent'),
                    'user_id' => (int)$this->child($child, 'wp', 'comment_user_id'),
                ];
            }
        }
        return [
            'title' => $this->child($node, '', 'title'),
            'link' => $this->child($node, '', 'link'),
            'guid' => $this->child($node, '', 'guid'),
            'creator' => $this->child($node, 'dc', 'creator'),
            'content' => $this->child($node, 'content', 'encoded', false),
            'excerpt' => $this->child($node, 'excerpt', 'encoded', false),
            'post_id' => (int)$this->child($node, 'wp', 'post_id'),
            'post_date' => $this->child($node, 'wp', 'post_date'),
            'post_date_gmt' => $this->child($node, 'wp', 'post_date_gmt'),
            'comment_status' => $this->child($node, 'wp', 'comment_status'),
            'post_name' => $this->child($node, 'wp', 'post_name'),
            'status' => $this->child($node, 'wp', 'status'),
            'post_parent' => (int)$this->child($node, 'wp', 'post_parent'),
            'post_type' => $this->child($node, 'wp', 'post_type'),
            'post_password' => $this->child($node, 'wp', 'post_password'),
            'is_sticky' => (int)$this->child($node, 'wp', 'is_sticky'),
            'attachment_url' => $this->child($node, 'wp', 'attachment_url'),
            'terms' => $terms,
            'meta' => $this->meta($node),
            'comments' => $comments,
        ];
    }

    /**
     * Calls $handler for every element directly below <channel> (site details, authors, terms and items).
     *
     * @param callable(string, \DOMElement): void $handler
     */
    private function walk(string $path, callable $handler): void
    {
        $reader = $this->open($path);
        try {
            $ok = $reader->read();
            $rootChecked = false;
            while ($ok) {
                if ($reader->nodeType === \XMLReader::ELEMENT) {
                    if (!$rootChecked) {
                        if ($reader->name !== 'rss') {
                            throw new \RuntimeException('The file is not a WordPress export (WXR) file.');
                        }
                        $rootChecked = true;
                    } elseif ($reader->depth === 2) {
                        $name = $reader->name;
                        $node = $reader->expand();
                        if ($node instanceof \DOMElement) {
                            $handler($name, $node);
                        }
                        $ok = $reader->next();
                        continue;
                    }
                }
                $ok = $reader->read();
            }
            $this->assertNoError();
            if (!$rootChecked) {
                throw new \RuntimeException('The file is empty or not XML.');
            }
        } finally {
            $reader->close();
        }
    }

    private function open(string $path): \XMLReader
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('The import file cannot be read.');
        }
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $reader = new \XMLReader();
        if (!$reader->open($path, null, self::LIBXML_OPTIONS)) {
            throw new \RuntimeException('The import file cannot be opened.');
        }
        // never substitute entities or load external DTDs
        $reader->setParserProperty(\XMLReader::SUBST_ENTITIES, false);
        $reader->setParserProperty(\XMLReader::LOADDTD, false);
        return $reader;
    }

    private function assertNoError(): void
    {
        foreach (libxml_get_errors() as $error) {
            if ($error->level >= LIBXML_ERR_ERROR) {
                libxml_clear_errors();
                throw new \RuntimeException(sprintf(
                    'The XML is not valid (line %d: %s).',
                    $error->line,
                    trim($error->message)
                ));
            }
        }
        libxml_clear_errors();
    }

    /**
     * Text of the first child element with the prefix and local name ('' prefix = no namespace).
     */
    private function child(\DOMElement $node, string $prefix, string $name, bool $trim = true): string
    {
        $qualified = $prefix === '' ? $name : $prefix . ':' . $name;
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->nodeName === $qualified) {
                return $trim ? trim($child->textContent) : $child->textContent;
            }
        }
        return '';
    }

    /**
     * @return array<string, string> post meta key => value (first value wins)
     */
    private function meta(\DOMElement $node): array
    {
        $meta = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->nodeName === 'wp:postmeta') {
                $key = $this->child($child, 'wp', 'meta_key');
                if ($key !== '' && !array_key_exists($key, $meta)) {
                    $meta[$key] = $this->child($child, 'wp', 'meta_value', false);
                }
            }
        }
        return $meta;
    }
}
