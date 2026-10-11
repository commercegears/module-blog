<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;

/**
 * A few sample posts, categories, tags and an author to see the blog working. Every sample record has an
 * external ID starting with "sample:", which is how remove() finds them; nothing else is touched.
 */
class SampleData
{
    public const PREFIX = 'sample:';
    private const NOTE = '<p><em>This is sample content from Softaware Blog. Edit or delete it under Softaware &gt; Blog &gt; Posts, '
        . 'or remove all sample content with <code>bin/magento softaware:blog:sample-data --remove</code>.</em></p>';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly AuthorFactory $authorFactory,
        private readonly ResourceModel\Author $authorResource,
        private readonly CategoryFactory $categoryFactory,
        private readonly ResourceModel\Category $categoryResource,
        private readonly TagFactory $tagFactory,
        private readonly ResourceModel\Tag $tagResource,
        private readonly PostFactory $postFactory,
        private readonly ResourceModel\Post $postResource,
        private readonly CommentFactory $commentFactory,
        private readonly ResourceModel\Comment $commentResource,
        private readonly ImageUploader $imageUploader,
        private readonly ProductResource $productResource,
        private readonly Filesystem $filesystem
    ) {
    }

    public function exists(): bool
    {
        $connection = $this->resourceConnection->getConnection();
        return (bool)$connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('softaware_blog_post'), ['post_id'])
                ->where('external_id LIKE ?', self::PREFIX . '%')
                ->limit(1)
        );
    }

    /**
     * @return array<string, int> created records by type
     */
    public function install(): array
    {
        $result = ['authors' => 0, 'categories' => 0, 'tags' => 0, 'posts' => 0, 'comments' => 0];
        if ($this->exists()) {
            return $result;
        }
        $author = $this->authorFactory->create();
        $author->setData([
            'name' => 'Alex Morgan',
            'url_key' => $this->freeKey('softaware_blog_author', 'alex-morgan'),
            'job_title' => 'Editor (sample author)',
            'bio' => 'Sample author created by Softaware Blog\'s sample data. Replace this text with a short biography.',
            'is_active' => 1,
            'external_id' => self::PREFIX . 'author:1',
        ]);
        $this->authorResource->save($author);
        $result['authors']++;

        $categories = [];
        foreach ([
            'guides' => ['Guides', null, 'Practical guides to choosing and using sportswear and gear.'],
            'yoga' => ['Yoga', 'guides', 'Getting started with yoga at home or in a class.'],
        ] as $key => [$name, $parent, $description]) {
            $category = $this->categoryFactory->create();
            $category->setData([
                'name' => $name,
                'url_key' => $this->freeKey('softaware_blog_category', $key),
                'parent_id' => $parent ? $categories[$parent] : null,
                'description' => '<p>' . $description . '</p>',
                'is_active' => 1,
                'position' => count($categories),
                'external_id' => self::PREFIX . 'category:' . $key,
            ]);
            $this->categoryResource->save($category);
            $categories[$key] = (int)$category->getId();
            $result['categories']++;
        }

        $tags = [];
        foreach (['beginners' => 'Beginners', 'outdoor' => 'Outdoor', 'running' => 'Running', 'yoga' => 'Yoga'] as $key => $name) {
            $tag = $this->tagFactory->create();
            $tag->setData([
                'name' => $name,
                'url_key' => $this->freeKey('softaware_blog_tag', $key),
                'external_id' => self::PREFIX . 'tag:' . $key,
            ]);
            $this->tagResource->save($tag);
            $tags[$key] = (int)$tag->getId();
            $result['tags']++;
        }

        $firstPostId = 0;
        foreach ($this->posts() as $index => $data) {
            $post = $this->postFactory->create();
            $image = $this->createImage($data['colours'], $data['url_key']);
            $post->setData([
                'title' => $data['title'],
                'url_key' => $this->freeKey('softaware_blog_post', $data['url_key']),
                'short_content' => $data['excerpt'],
                'content' => $data['content'] . self::NOTE,
                'featured_image' => $image ?: null,
                'featured_image_alt' => $data['alt'],
                'author_id' => (int)$author->getId(),
                'status' => Post::STATUS_PUBLISHED,
                'publish_at' => gmdate('Y-m-d H:i:s', time() - (3 + $index * 7) * 86400),
                'is_featured' => $index === 0 ? 1 : 0,
                'allow_comments' => 1,
                'meta_title' => $data['meta_title'],
                'meta_description' => $data['meta_description'],
                'focus_keyword' => $data['keyword'],
                'external_id' => self::PREFIX . 'post:' . ($index + 1),
            ]);
            $post->setStoreIds([0]);
            $post->setCategoryIds(array_map(static fn ($key) => $categories[$key], $data['categories']));
            $post->setTagIds(array_map(static fn ($key) => $tags[$key], $data['tags']));
            $post->setProductIds($this->productIds($data['skus']));
            $this->postResource->save($post);
            $firstPostId = $firstPostId ?: (int)$post->getId();
            $result['posts']++;
        }

        $comment = $this->commentFactory->create();
        $comment->setData([
            'post_id' => $firstPostId,
            'author_name' => 'Sam (sample comment)',
            'author_email' => null,
            'content' => 'Thanks, the part about thickness was useful. This is a sample comment.',
            'status' => Comment::STATUS_APPROVED,
            'external_id' => self::PREFIX . 'comment:1',
        ]);
        $this->commentResource->save($comment);
        $result['comments']++;
        return $result;
    }

    /**
     * Deletes every record whose external ID starts with "sample:" and the sample images.
     *
     * @return array<string, int> deleted records by type
     */
    public function remove(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $postTable = $this->resourceConnection->getTableName('softaware_blog_post');
        $images = $connection->fetchCol(
            $connection->select()->from($postTable, ['featured_image'])
                ->where('external_id LIKE ?', self::PREFIX . '%')
                ->where('featured_image LIKE ?', 'sample/%')
        );
        $result = [];
        foreach ([
            'comments' => 'softaware_blog_comment',
            'posts' => 'softaware_blog_post',
            'tags' => 'softaware_blog_tag',
            'authors' => 'softaware_blog_author',
        ] as $type => $table) {
            $result[$type] = $connection->delete(
                $this->resourceConnection->getTableName($table),
                ['external_id LIKE ?' => self::PREFIX . '%']
            );
        }
        // subcategories first (parent_id is set to NULL on delete, so the order only keeps the count right)
        $result['categories'] = $connection->delete(
            $this->resourceConnection->getTableName('softaware_blog_category'),
            ['external_id LIKE ?' => self::PREFIX . '%']
        );
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        foreach ($images as $image) {
            $path = ImageUploader::BASE_PATH . '/' . str_replace('..', '', (string)$image);
            if ($media->isFile($path)) {
                $media->delete($path);
            }
        }
        return $result;
    }

    /**
     * @param string[] $skus
     * @return int[]
     */
    private function productIds(array $skus): array
    {
        $ids = [];
        foreach ($skus as $sku) {
            $id = (int)$this->productResource->getIdBySku($sku);
            if ($id) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private function freeKey(string $table, string $key): string
    {
        $connection = $this->resourceConnection->getConnection();
        $candidate = $key;
        for ($i = 2; $i < 100; $i++) {
            $taken = $connection->fetchOne(
                $connection->select()->from($this->resourceConnection->getTableName($table), [new \Zend_Db_Expr('1')])
                    ->where('url_key = ?', $candidate)->limit(1)
            );
            if (!$taken) {
                return $candidate;
            }
            $candidate = $key . '-sample' . ($i > 2 ? '-' . $i : '');
        }
        return $key . '-' . substr(sha1(uniqid('', true)), 0, 6);
    }

    /**
     * A plain gradient picture (1200 x 675) so the sample posts have featured images; '' without the GD extension.
     *
     * @param array{0: string, 1: string} $colours
     */
    private function createImage(array $colours, string $name): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return '';
        }
        $width = 1200;
        $height = 675;
        $image = imagecreatetruecolor($width, $height);
        [$from, $to] = array_map(static fn ($hex) => sscanf($hex, '#%02x%02x%02x'), $colours);
        for ($y = 0; $y < $height; $y++) {
            $t = $y / ($height - 1);
            $colour = imagecolorallocate(
                $image,
                (int)round($from[0] + ($to[0] - $from[0]) * $t),
                (int)round($from[1] + ($to[1] - $from[1]) * $t),
                (int)round($from[2] + ($to[2] - $from[2]) * $t)
            );
            imageline($image, 0, $y, $width, $y, $colour);
        }
        $light = imagecolorallocatealpha($image, 255, 255, 255, 100);
        imagefilledellipse($image, 930, 180, 520, 520, $light);
        imagefilledellipse($image, 260, 560, 380, 380, $light);
        ob_start();
        imagejpeg($image, null, 82);
        $bytes = (string)ob_get_clean();
        unset($image);
        try {
            return $this->imageUploader->saveContent($bytes, $name . '.jpg', 'sample');
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function posts(): array
    {
        return [
            [
                'title' => 'How to choose a yoga mat',
                'url_key' => 'how-to-choose-a-yoga-mat',
                'keyword' => 'yoga mat',
                'meta_title' => 'How to choose a yoga mat: thickness, grip and size',
                'meta_description' => 'What to look at when you buy a yoga mat: thickness for comfort, a surface with grip, the right length for your height and how to keep it clean.',
                'excerpt' => 'Thickness, grip, size and care: the four things to check before you buy a yoga mat.',
                'alt' => 'Soft green gradient (sample image)',
                'colours' => ['#7fb59a', '#2f6b5b'],
                'categories' => ['yoga'],
                'tags' => ['yoga', 'beginners'],
                'skus' => ['24-WG084', '24-WG085', '24-WB01'],
                'content' => '<p>A yoga mat is the one piece of kit you use in every session, so it is worth choosing with some care. This guide covers the four things to check before you buy a yoga mat.</p>'
                    . '<h2>Thickness</h2><p>Thinner mats give you a firm connection to the floor, which helps with balance. Thicker mats are kinder to knees and wrists on hard floors. If you practise at home on wooden boards, a little more cushioning is usually more comfortable.</p>'
                    . '<h2>Grip</h2><p>Your hands and feet should stay where you put them, even when you warm up. Try the surface with dry and slightly damp hands if you can.</p>'
                    . '<h2>Size</h2><p>When you lie down, your head and heels should both be on the mat. Taller people may want a longer mat.</p>'
                    . '<h2>Care</h2><p>Wipe the mat after each session and let it dry flat before rolling it up. A <a href="{{store url=""}}">yoga strap or brick</a> can also help while you learn new poses.</p>',
            ],
            [
                'title' => 'Layering for cold-weather runs',
                'url_key' => 'layering-for-cold-weather-runs',
                'keyword' => 'cold-weather running',
                'meta_title' => 'Layering for cold-weather running: base, mid and outer layer',
                'meta_description' => 'A simple three-layer system for cold-weather running: a base layer that moves sweat away, a warm mid layer and a jacket that blocks wind and rain.',
                'excerpt' => 'Base layer, mid layer, outer layer: a simple system that keeps you comfortable on cold runs.',
                'alt' => 'Cool blue gradient (sample image)',
                'colours' => ['#8fb3d9', '#2c4f7c'],
                'categories' => ['guides'],
                'tags' => ['running', 'outdoor'],
                'skus' => ['MS11', 'MJ04', 'MH13'],
                'content' => '<p>Cold-weather running is more pleasant when you dress in layers that you can adjust as you warm up.</p>'
                    . '<h2>Base layer</h2><p>Start with a close-fitting top in a technical fabric that moves sweat away from your skin.</p>'
                    . '<h2>Mid layer</h2><p>On colder days add a light, breathable layer such as a thin hoodie.</p>'
                    . '<h2>Outer layer</h2><p>A <a href="{{store url=""}}">lightweight jacket</a> blocks wind and light rain. Choose one you can unzip as you warm up.</p>'
                    . '<p>Start your run feeling slightly cool: you will warm up within the first few minutes.</p>',
            ],
            [
                'title' => 'Starting yoga at home',
                'url_key' => 'starting-yoga-at-home',
                'keyword' => 'yoga at home',
                'meta_title' => 'Starting yoga at home: space, routine and kit',
                'meta_description' => 'Tips for starting yoga at home: find a quiet space, keep sessions short and regular, and gather a few simple pieces of kit before you begin.',
                'excerpt' => 'Find a quiet corner, keep sessions short and regular, and start with a few simple pieces of kit.',
                'alt' => 'Soft lilac gradient (sample image)',
                'colours' => ['#c3b1e1', '#5d4a8a'],
                'categories' => ['yoga', 'guides'],
                'tags' => ['yoga', 'beginners'],
                'skus' => ['24-WG080', 'MP10'],
                'content' => '<p>Practising yoga at home fits easily around a busy day. A few preparations make it easier to keep going.</p>'
                    . '<h2>Find your space</h2><p>You need enough room to stretch your arms out in every direction. A quiet corner works well.</p>'
                    . '<h2>Short and regular</h2><p>Short sessions that you repeat are easier to keep up than long ones you put off.</p>'
                    . '<h2>Simple kit</h2><p>A mat, comfortable clothes and perhaps a <a href="{{store url=""}}">strap or block</a> are all you need to begin.</p>',
            ],
        ];
    }
}
