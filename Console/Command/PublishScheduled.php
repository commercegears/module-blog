<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Console\Command;

use Softaware\Blog\Model\SchedulePublisher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Publishes the scheduled posts whose date has passed (the cron job does the same every few minutes).
 */
class PublishScheduled extends Command
{
    public function __construct(
        private readonly SchedulePublisher $publisher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('softaware:blog:publish-scheduled')
            ->setDescription('Refresh the caches of scheduled blog posts whose publish date has passed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ids = $this->publisher->run();
        $output->writeln($ids
            ? sprintf('%d scheduled post(s) went live: %s', count($ids), implode(', ', $ids))
            : 'No scheduled posts were due.');
        return Command::SUCCESS;
    }
}
