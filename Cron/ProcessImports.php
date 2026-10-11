<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Cron;

use CommerceGears\Blog\Model\Import\JobRunner;

/**
 * Runs WordPress imports queued in the admin (one per run).
 */
class ProcessImports
{
    public function __construct(private readonly JobRunner $jobRunner)
    {
    }

    public function execute(): void
    {
        $this->jobRunner->runNext();
    }
}
