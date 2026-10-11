<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Cron;

use Softaware\Blog\Model\Import\JobRunner;

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
