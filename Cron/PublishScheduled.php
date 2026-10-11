<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Cron;

use Softaware\Blog\Model\SchedulePublisher;

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
