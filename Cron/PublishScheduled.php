<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Cron;

use CommerceGears\Blog\Model\SchedulePublisher;

class PublishScheduled
{
    public function __construct(private readonly SchedulePublisher $publisher)
    {
    }

    public function execute(): void
    {
        $this->publisher->run();
    }
}
