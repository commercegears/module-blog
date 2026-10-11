<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Import\WordPress;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use CommerceGears\Blog\Model\AuthorFactory;
use CommerceGears\Blog\Model\CategoryFactory;
use CommerceGears\Blog\Model\CommentFactory;
use CommerceGears\Blog\Model\ImageUploader;
use CommerceGears\Blog\Model\Import\FileStorage;
use CommerceGears\Blog\Model\Import\ImageDownloader;
use CommerceGears\Blog\Model\Import\RedirectWriter;
use CommerceGears\Blog\Model\ImportJob;
use CommerceGears\Blog\Model\Post;
use CommerceGears\Blog\Model\PostFactory;
use CommerceGears\Blog\Model\ResourceModel\Author as AuthorResource;
use CommerceGears\Blog\Model\ResourceModel\Category as CategoryResource;
use CommerceGears\Blog\Model\ResourceModel\Comment as CommentResource;
use CommerceGears\Blog\Model\ResourceModel\Post as PostResource;
use CommerceGears\Blog\Model\ResourceModel\Tag as TagResource;
use CommerceGears\Blog\Model\TagFactory;
use CommerceGears\Blog\Model\Url;
use CommerceGears\Blog\Model\UrlKeyFormatter;
use CommerceGears\Blog\Model\UrlReservedWords;

/**
 * Imports a WordPress export in steps, so large files are spread over several cron runs:
 *
 * 1. scan: site details, authors, categories, tags and attachment addresses are read into a progress file;
 * 2. terms: authors, categories (with their parents) and tags are created;
 * 3. posts: posts with their images, comments and redirects, a batch at a time (the position is saved).
 *
 * Every record gets an external ID ("wp:<site>:<type>:<id>"), so running the same file again creates nothing
 * twice: posts imported before are skipped (or updated when the job says so), comments and redirects are
 * only added once.
 */
class Importer
{
    public const PHASE_SCAN = 'scan';
    public const PHASE_TERMS = 'terms';
    public const PHASE_POSTS = 'posts';
    public const PHASE_DONE = 'done';

    private const LOG_MAX_LINES = 3000;

    /** @var array<string, int> */
    private array $idCache = [];

    /** @var array<string, mixed> */
    private array $state = [];

    /** @var array<string, int> */
    private array $stats = [];

    /** @var string[] */
    private array $logLines = [];

    /** @var callable|null */
    private $output = null;

    public function __construct(
        private readonly WxrReader $reader,
        private readonly ItemMapper $mapper,
        private readonly ContentConverter $converter,
        private readonly ImageDownloader $downloader,
        private readonly ImageUploader $imageUploader,
        private readonly RedirectWriter $redirectWriter,
        private readonly FileStorage $fileStorage,
        private readonly ResourceConnection $resourceConnection,
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryResource $categoryResource,
        private readonly TagFactory $tagFactory,
        private readonly TagResource $tagResource,
        private readonly AuthorFactory $authorFactory,
        private readonly AuthorResource $authorResource,
        private readonly CommentFactory $commentFactory,
        private readonly CommentResource $commentResource,
        private readonly UrlKeyFormatter $urlKeyFormatter,
        private readonly UrlReservedWords $reservedWords,
        private readonly Url $url,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Runs the next steps of a job until it is finished or the time or item budget is used up.
     *
     * The job's stats, log and progress are updated on the job object; the caller saves it.
     *
     * @param callable(string): void|null $output receives every log line (command line)
     * @return bool true when the import is complete
     * @throws \RuntimeException when the file cannot be read (the job fails)
     */
    public function runBatch(ImportJob $job, int $seconds = 50, int $maxPosts = 200, ?callable $output = null): bool
    {
        $this->output = $output;
        $this->logLines = [];
        $this->idCache = [];
        $jobId = (int)$job->getId();
        $deadline = microtime(true) + max(5, $seconds);
        $this->stats = $job->getStats() + ['phase' => self::PHASE_SCAN, 'position' => 0];
        $this->state = $this->fileStorage->readState($jobId);
        $options = $job->getOptions();
        $file = $this->fileStorage->getPath((string)$job->getData('file_name'));

        try {
            if ($this->stats['phase'] === self::PHASE_SCAN) {
                $this->scan($file);
                $this->stats['phase'] = self::PHASE_TERMS;
            }
            if ($this->stats['phase'] === self::PHASE_TERMS) {
                $this->importTerms();
                $this->stats['phase'] = self::PHASE_POSTS;
                $this->stats['position'] = 0;
            }
            if ($this->stats['phase'] === self::PHASE_POSTS) {
                $finished = $this->importPosts($file, $options, $deadline, $maxPosts);
                if (!$finished) {
                    $this->log(sprintf(
                        'Progress: %d of %d posts; continuing with the next run.',
                        $this->stat('posts_created') + $this->stat('posts_updated') + $this->stat('posts_skipped') + $this->stat('errors'),
                        $this->stat('posts_total')
                    ));
                }
                if ($finished) {
                    $this->stats['phase'] = self::PHASE_DONE;
                    $this->log(sprintf(
                        'Finished: %d posts created, %d updated, %d skipped, %d comments, %d images, %d redirects, %d errors.',
                        $this->stat('posts_created'),
                        $this->stat('posts_updated'),
                        $this->stat('posts_skipped'),
                        $this->stat('comments'),
                        $this->stat('images'),
                        $this->stat('redirects'),
                        $this->stat('errors')
                    ));
                }
            }
        } finally {
            $this->fileStorage->writeState($jobId, $this->state);
            $job->setData('stats', (string)json_encode($this->stats));
            $job->setData('log', $this->appendLog((string)$job->getData('log')));
        }
        return $this->stats['phase'] === self::PHASE_DONE;
    }

    /**
     * @return array<string, int|string>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    private function scan(string $file): void
    {
        $scan = $this->reader->scan($file);
        $siteUrl = $scan['site']['base_blog_url'] ?: ($scan['site']['base_site_url'] ?: $scan['site']['link']);
        $this->state = [
            'site' => $scan['site'],
            'site_key' => $this->mapper->siteKey($siteUrl),
            'site_hosts' => $this->mapper->siteHosts($scan['site']),
            'authors' => $scan['authors'],
            'categories' => $scan['categories'],
            'tags' => $scan['tags'],
            'attachments' => $scan['attachments'],
            'images' => [],
        ];
        $this->stats['items_total'] = $scan['items'];
        $this->stats['posts_total'] = $scan['posts'];
        $this->log(sprintf(
            'Read "%s" (%s): %d posts, %d categories, %d tags, %d authors, %d attachments.',
            $scan['site']['title'],
            $siteUrl,
            $scan['posts'],
            count($scan['categories']),
            count($scan['tags']),
            count($scan['authors']),
            count($scan['attachments'])
        ));
    }

    private function importTerms(): void
    {
        foreach ((array)($this->state['authors'] ?? []) as $author) {
            $this->resolveAuthor((string)$author['login']);
        }
        $parents = [];
        foreach ((array)($this->state['categories'] ?? []) as $category) {
            $slug = rawurldecode((string)$category['slug']);
            if ($slug === ItemMapper::DEFAULT_CATEGORY) {
                continue;
            }
            $id = $this->resolveCategory($slug, html_entity_decode((string)$category['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), (string)$category['description']);
            if ($id && (string)$category['parent'] !== '') {
                $parents[$id] = rawurldecode((string)$category['parent']);
            }
        }
        foreach ($parents as $id => $parentSlug) {
            $parentId = $this->resolveCategory($parentSlug, $parentSlug);
            if (!$parentId || $parentId === $id) {
                continue;
            }
            $category = $this->categoryFactory->create();
            $this->categoryResource->load($category, $id);
            if ($category->getId() && !$category->getParentId() && $category->getData('external_id')) {
                try {
                    $category->setData('parent_id', $parentId);
                    $this->categoryResource->save($category);
                } catch (\Exception $e) {
                    $this->error(sprintf('Category "%s": %s', $category->getName(), $e->getMessage()));
                }
            }
        }
        foreach ((array)($this->state['tags'] ?? []) as $tag) {
            $this->resolveTag(rawurldecode((string)$tag['slug']), html_entity_decode((string)$tag['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $this->log(sprintf(
            'Authors, categories and tags ready (%d authors, %d categories and %d tags created).',
            $this->stat('authors'),
            $this->stat('categories'),
            $this->stat('tags')
        ));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function importPosts(string $file, array $options, float $deadline, int $maxPosts): bool
    {
        $done = 0;
        foreach ($this->reader->items($file, (int)$this->stats['position']) as $position => $item) {
            if ($done >= $maxPosts || microtime(true) >= $deadline) {
                return false;
            }
            $this->stats['position'] = $position + 1;
            if (!$this->mapper->isPost($item)) {
                continue;
            }
            $done++;
            try {
                $this->importPost($item, $options);
            } catch (\Throwable $e) {
                $this->error(sprintf('Post "%s" (WordPress ID %d): %s', $item['title'], $item['post_id'], $e->getMessage()));
            }
        }
        return true;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $options
     */
    private function importPost(array $item, array $options): void
    {
        $siteKey = (string)$this->state['site_key'];
        $externalId = $this->mapper->externalId($siteKey, 'post', (int)$item['post_id']);
        $postId = $this->findId('commercegears_blog_post', 'post_id', $externalId);
        $title = trim(html_entity_decode((string)$item['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($title === '') {
            $title = 'Untitled post ' . (int)$item['post_id'];
        }
        $storeIds = array_values(array_map('intval', (array)($options['store_ids'] ?? [0]))) ?: [0];

        /** @var Post $post */
        $post = $this->postFactory->create();
        if ($postId) {
            $this->postResource->load($post, $postId);
        }
        if ($postId && empty($options['update_existing'])) {
            $this->bump('posts_skipped');
        } else {
            $content = $this->converter->convert((string)$item['content']);
            $featured = '';
            $featuredAlt = '';
            if (!empty($options['download_images'])) {
                $content = $this->localiseImages($content, $title);
                [$featured, $featuredAlt] = $this->featuredImage($item);
            }
            foreach ($this->converter->findShortcodes($content) as $shortcode) {
                $this->state['shortcodes'][$shortcode] = ($this->state['shortcodes'][$shortcode] ?? 0) + 1;
            }
            $status = $this->mapper->mapStatus($item, !empty($options['as_drafts']));
            $seo = $this->mapper->mapSeo((array)$item['meta']);
            $data = [
                'title' => mb_substr($title, 0, 255),
                'short_content' => trim($this->converter->convert((string)$item['excerpt'])) ?: null,
                'content' => $content,
                'status' => $status['status'],
                'publish_at' => $status['publish_at'],
                'is_featured' => (int)$item['is_sticky'] ? 1 : 0,
                'allow_comments' => $item['comment_status'] === 'closed' ? 0 : 1,
                'author_id' => $this->resolveAuthor((string)$item['creator']) ?: null,
                'meta_title' => $seo['meta_title'] ?: null,
                'meta_description' => $seo['meta_description'] ?: null,
                'focus_keyword' => $seo['focus_keyword'] ?: null,
                'meta_robots' => $seo['meta_robots'] ?: null,
                'external_id' => $externalId,
            ];
            if ($featured !== '') {
                $data['featured_image'] = $featured;
                $data['featured_image_alt'] = $featuredAlt ?: $title;
            }
            $categoryIds = [];
            foreach ($this->mapper->terms($item, 'category') as $term) {
                $categoryIds[] = $this->resolveCategory($term['slug'], $term['name']);
            }
            $tagIds = [];
            foreach ($this->mapper->terms($item, 'post_tag') as $term) {
                $tagIds[] = $this->resolveTag($term['slug'], $term['name']);
            }
            if (!$postId) {
                $data['url_key'] = $this->uniquePostKey($this->mapper->slug($item) ?: $title);
                $post->setData($data);
                $post->setStoreIds($storeIds);
            } else {
                $post->addData($data);
            }
            $post->setCategoryIds(array_filter($categoryIds));
            $post->setTagIds(array_filter($tagIds));
            $this->postResource->save($post);
            $this->bump($postId ? 'posts_updated' : 'posts_created');
            $postId = (int)$post->getId();
        }

        if (!empty($options['comments']) && $item['comments']) {
            $this->importComments($postId, $item['comments'], $storeIds);
        }
        if (!empty($options['redirects'])) {
            $this->addRedirects((string)$item['link'], $post, $storeIds);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $comments
     * @param int[] $storeIds
     */
    private function importComments(int $postId, array $comments, array $storeIds): void
    {
        $siteKey = (string)$this->state['site_key'];
        usort($comments, static fn ($a, $b) => (int)$a['id'] <=> (int)$b['id']);
        $storeId = (int)(array_values(array_filter($storeIds))[0] ?? 0);
        foreach ($comments as $comment) {
            $externalId = $this->mapper->externalId($siteKey, 'comment', (int)$comment['id']);
            if ($this->findId('commercegears_blog_comment', 'comment_id', $externalId)) {
                continue;
            }
            $data = $this->mapper->mapComment($comment);
            if ($data === null) {
                continue;
            }
            $parentId = 0;
            if ((int)$comment['parent'] > 0) {
                $parentId = $this->findId(
                    'commercegears_blog_comment',
                    'comment_id',
                    $this->mapper->externalId($siteKey, 'comment', (int)$comment['parent'])
                );
            }
            $model = $this->commentFactory->create();
            $model->setData($data + [
                'post_id' => $postId,
                'parent_id' => $parentId ?: null,
                'store_id' => $storeId ?: null,
                'external_id' => $externalId,
            ]);
            $this->commentResource->save($model);
            $this->idCache['commercegears_blog_comment|' . $externalId] = (int)$model->getId();
            $this->bump('comments');
        }
    }

    /**
     * @param int[] $storeIds
     */
    private function addRedirects(string $link, Post $post, array $storeIds): void
    {
        $oldPath = $this->mapper->oldPath($link);
        if ($oldPath === null) {
            return;
        }
        $clash = false;
        foreach ($this->targetStores($storeIds) as $storeId) {
            $target = $this->url->getPostPath($post->getUrlKey(), $storeId);
            // Magento's URL finder matches the address with and without the trailing slash
            $result = $this->redirectWriter->add($oldPath, $target, $storeId, 'CommerceGears Blog: WordPress import');
            if ($result === RedirectWriter::CREATED) {
                $this->bump('redirects');
            } elseif ($result === RedirectWriter::SKIPPED) {
                $clash = true;
            }
        }
        if ($clash) {
            $this->log(sprintf('No redirect for /%s (it would clash with a store address).', $oldPath));
        }
    }

    /**
     * @param int[] $storeIds
     * @return int[]
     */
    private function targetStores(array $storeIds): array
    {
        if (!in_array(0, $storeIds, true)) {
            return $storeIds;
        }
        $ids = [];
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $ids[] = (int)$store->getId();
            }
        }
        return $ids;
    }

    /**
     * Downloads images of the WordPress site used in the content and points the content at the copies.
     */
    private function localiseImages(string $content, string $title): string
    {
        $map = [];
        $siteHosts = (array)$this->state['site_hosts'];
        foreach ($this->converter->findImageUrls($content) as $url) {
            if (!$this->mapper->isSiteImage($url, $siteHosts)) {
                continue;
            }
            $file = $this->downloadImage($url, $title);
            if ($file !== '') {
                $map[$url] = '{{media url="' . ImageUploader::BASE_PATH . '/' . $file . '"}}';
            }
        }
        return $this->converter->replaceImageUrls($content, $map);
    }

    /**
     * @param array<string, mixed> $item
     * @return array{0: string, 1: string} file, alt text
     */
    private function featuredImage(array $item): array
    {
        $attachmentId = (int)($item['meta']['_thumbnail_id'] ?? 0);
        $attachment = $this->state['attachments'][$attachmentId] ?? $this->state['attachments'][(string)$attachmentId] ?? null;
        if (!$attachment || empty($attachment['url'])) {
            return ['', ''];
        }
        $file = $this->downloadImage((string)$attachment['url'], (string)$item['title']);
        return [$file, (string)($attachment['alt'] ?? '')];
    }

    /**
     * @return string path below pub/media/commercegears_blog/, '' when the image could not be downloaded
     */
    private function downloadImage(string $url, string $context): string
    {
        if (isset($this->state['images'][$url])) {
            return (string)$this->state['images'][$url];
        }
        try {
            $bytes = $this->downloader->download($url);
            $file = $this->imageUploader->saveContent($bytes, $this->mapper->imageFileName($url), 'post');
            $this->state['images'][$url] = $file;
            $this->bump('images');
            return $file;
        } catch (\Throwable $e) {
            $this->bump('image_errors');
            $this->log(sprintf('Image not imported for "%s": %s (%s)', $context, $url, $e->getMessage()));
            // remember the failure so the same address is not tried again for every post
            $this->state['images'][$url] = '';
            return '';
        }
    }

    private function resolveAuthor(string $login): int
    {
        $login = trim($login);
        if ($login === '') {
            return 0;
        }
        $externalId = $this->mapper->externalId((string)$this->state['site_key'], 'author', $login);
        $id = $this->findId('commercegears_blog_author', 'author_id', $externalId);
        if ($id) {
            return $id;
        }
        $details = [];
        foreach ((array)($this->state['authors'] ?? []) as $author) {
            if ((string)$author['login'] === $login) {
                $details = $author;
                break;
            }
        }
        $name = trim(html_entity_decode((string)($details['display_name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ?: trim(($details['first_name'] ?? '') . ' ' . ($details['last_name'] ?? ''))
            ?: $login;
        $connection = $this->resourceConnection->getConnection();
        $existing = (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('commercegears_blog_author'), ['author_id'])
                ->where('LOWER(name) = ?', mb_strtolower($name))
                ->limit(1)
        );
        if ($existing) {
            return $this->idCache['commercegears_blog_author|' . $externalId] = $existing;
        }
        $author = $this->authorFactory->create();
        $author->setData([
            'name' => mb_substr($name, 0, 255),
            'url_key' => $this->uniqueKey('commercegears_blog_author', 'author_id', $this->urlKeyFormatter->format($name) ?: 'author'),
            'is_active' => 1,
            'external_id' => $externalId,
        ]);
        $this->authorResource->save($author);
        $this->bump('authors');
        return $this->idCache['commercegears_blog_author|' . $externalId] = (int)$author->getId();
    }

    private function resolveCategory(string $slug, string $name, string $description = ''): int
    {
        $key = $slug !== '' ? $slug : $name;
        if ($key === '' || $key === ItemMapper::DEFAULT_CATEGORY) {
            return 0;
        }
        $externalId = $this->mapper->externalId((string)$this->state['site_key'], 'category', $key);
        $id = $this->findId('commercegears_blog_category', 'category_id', $externalId);
        if ($id) {
            return $id;
        }
        $urlKey = $this->urlKeyFormatter->format($key) ?: $this->urlKeyFormatter->format($name);
        $existing = $urlKey !== '' ? $this->findIdBy('commercegears_blog_category', 'category_id', 'url_key', $urlKey) : 0;
        if ($existing) {
            return $this->idCache['commercegears_blog_category|' . $externalId] = $existing;
        }
        $category = $this->categoryFactory->create();
        $category->setData([
            'name' => mb_substr($name !== '' ? $name : $slug, 0, 255),
            'url_key' => $urlKey ?: null,
            'description' => trim($description) !== '' ? $this->converter->convert($description) : null,
            'is_active' => 1,
            'external_id' => $externalId,
        ]);
        $this->categoryResource->save($category);
        $this->bump('categories');
        return $this->idCache['commercegears_blog_category|' . $externalId] = (int)$category->getId();
    }

    private function resolveTag(string $slug, string $name): int
    {
        $key = $slug !== '' ? $slug : $name;
        if ($key === '') {
            return 0;
        }
        $externalId = $this->mapper->externalId((string)$this->state['site_key'], 'tag', $key);
        $id = $this->findId('commercegears_blog_tag', 'tag_id', $externalId);
        if ($id) {
            return $id;
        }
        $urlKey = $this->urlKeyFormatter->format($key) ?: $this->urlKeyFormatter->format($name);
        $connection = $this->resourceConnection->getConnection();
        $existing = (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('commercegears_blog_tag'), ['tag_id'])
                ->where('url_key = ? OR LOWER(name) = ' . $connection->quote(mb_strtolower($name !== '' ? $name : $slug)), $urlKey)
                ->limit(1)
        );
        if ($existing) {
            return $this->idCache['commercegears_blog_tag|' . $externalId] = $existing;
        }
        $tag = $this->tagFactory->create();
        $tag->setData([
            'name' => mb_substr($name !== '' ? $name : $slug, 0, 255),
            'url_key' => $urlKey ?: null,
            'external_id' => $externalId,
        ]);
        $this->tagResource->save($tag);
        $this->bump('tags');
        return $this->idCache['commercegears_blog_tag|' . $externalId] = (int)$tag->getId();
    }

    private function uniquePostKey(string $value): string
    {
        $base = $this->urlKeyFormatter->format($value) ?: 'post';
        $key = $base;
        for ($i = 2; $i < 1000; $i++) {
            if (!$this->reservedWords->isReserved($key, 0) && !$this->postResource->isUrlKeyTaken($key, 0)) {
                return $key;
            }
            $key = mb_substr($base, 0, 190) . '-' . $i;
        }
        return $base . '-' . substr(sha1(uniqid('', true)), 0, 6);
    }

    private function uniqueKey(string $table, string $idField, string $base): string
    {
        $key = $base;
        for ($i = 2; $i < 1000 && $this->findIdBy($table, $idField, 'url_key', $key); $i++) {
            $key = mb_substr($base, 0, 190) . '-' . $i;
        }
        return $key;
    }

    private function findId(string $table, string $idField, string $externalId): int
    {
        $cacheKey = $table . '|' . $externalId;
        if (!isset($this->idCache[$cacheKey])) {
            $id = $this->findIdBy($table, $idField, 'external_id', $externalId);
            if (!$id) {
                return 0;
            }
            $this->idCache[$cacheKey] = $id;
        }
        return $this->idCache[$cacheKey];
    }

    private function findIdBy(string $table, string $idField, string $column, string $value): int
    {
        $connection = $this->resourceConnection->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName($table), [$idField])
                ->where($column . ' = ?', $value)
                ->limit(1)
        );
    }

    private function bump(string $key): void
    {
        $this->stats[$key] = $this->stat($key) + 1;
    }

    private function stat(string $key): int
    {
        return (int)($this->stats[$key] ?? 0);
    }

    private function error(string $message): void
    {
        $this->bump('errors');
        $this->log('Error: ' . $message);
    }

    private function log(string $message): void
    {
        $line = gmdate('Y-m-d H:i:s') . ' ' . $message;
        $this->logLines[] = $line;
        if ($this->output) {
            ($this->output)($message);
        }
    }

    private function appendLog(string $existing): string
    {
        if (!$this->logLines) {
            return $existing;
        }
        if (!empty($this->state['shortcodes']) && ($this->stats['phase'] ?? '') === self::PHASE_DONE) {
            $list = [];
            foreach ($this->state['shortcodes'] as $code => $count) {
                $list[] = '[' . $code . '] x' . $count;
            }
            $this->logLines[] = gmdate('Y-m-d H:i:s') . ' WordPress shortcodes left in the content (check these posts): '
                . implode(', ', $list) . '.';
        }
        $lines = array_merge($existing === '' ? [] : explode("\n", $existing), $this->logLines);
        if (count($lines) > self::LOG_MAX_LINES) {
            $lines = array_slice($lines, -self::LOG_MAX_LINES);
        }
        return implode("\n", $lines);
    }
}
