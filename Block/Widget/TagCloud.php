<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block\Widget;

use Softaware\Blog\Model\Tag;

/**
 * Widget "Softaware Blog: Tags" (most used tags, sized by use).
 */
class TagCloud extends AbstractWidget
{
    protected const TEMPLATES = [
        'Softaware_Blog::widget/tags.phtml' => 'Softaware_Blog::hyva/widget/tags.phtml',
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
