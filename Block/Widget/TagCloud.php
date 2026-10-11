<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Block\Widget;

use CommerceGears\Blog\Model\Tag;

/**
 * Widget "CommerceGears Blog: Tags" (most used tags, sized by use).
 */
class TagCloud extends AbstractWidget
{
    protected const TEMPLATES = [
        'CommerceGears_Blog::widget/tags.phtml' => 'CommerceGears_Blog::hyva/widget/tags.phtml',
    ];

    /**
     * @return array<int, array{tag: Tag, count: int, weight: int}> weight 1 to 4
     */
    public function getTags(): array
    {
        $limit = max(1, min(100, (int)($this->getData('tags_count') ?: 20)));
        $tags = $this->catalog->getPopularTags($this->getStoreId(), $limit);
        if (!$tags) {
            return [];
        }
        $counts = array_column($tags, 'count');
        $min = min($counts);
        $max = max($counts);
        $result = [];
        foreach ($tags as $id => $row) {
            $row['weight'] = $max === $min ? 2 : 1 + (int)round(3 * ($row['count'] - $min) / ($max - $min));
            $result[$id] = $row;
        }
        uasort($result, static fn ($a, $b) => strcasecmp($a['tag']->getName(), $b['tag']->getName()));
        return $result;
    }
}
