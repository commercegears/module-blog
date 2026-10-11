<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Comment extends AbstractDb
{
    public const TABLE = 'commercegears_blog_comment';

    protected function _construct()
    {
        $this->_init(self::TABLE, 'comment_id');
    }

    public function countRecentByIpHash(string $ipHash, string $sinceUtc): int
    {
        $connection = $this->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), [new \Zend_Db_Expr('COUNT(*)')])
                ->where('ip_hash = ?', $ipHash)
                ->where('created_at >= ?', $sinceUtc)
        );
    }

    /**
     * @param int[] $postIds
     * @return array<int, int> post_id => approved comments
     */
    public function countApproved(array $postIds): array
    {
        if (!$postIds) {
            return [];
        }
        $connection = $this->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($this->getMainTable(), ['post_id', new \Zend_Db_Expr('COUNT(*)')])
                ->where('post_id IN (?)', $postIds)
                ->where('status = 1')
                ->group('post_id')
        );
        return array_map('intval', $rows);
    }
}
