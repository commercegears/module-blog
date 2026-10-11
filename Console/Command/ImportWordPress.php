<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Softaware\Blog\Model\Import\JobRunner;
use Softaware\Blog\Model\Import\WordPress\Importer;
use Softaware\Blog\Model\ImportJob;
use Softaware\Blog\Model\ImportJobFactory;
use Softaware\Blog\Model\ResourceModel\ImportJob as ImportJobResource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Imports a WordPress export file (WXR) on the command line, or runs a job queued in the admin.
 */
class ImportWordPress extends Command
{
    public function __construct(
        private readonly JobRunner $jobRunner,
        private readonly ImportJobFactory $jobFactory,
        private readonly ImportJobResource $jobResource,
        private readonly AppState $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('softaware:blog:import-wordpress')
            ->setDescription('Import posts, categories, tags, authors, comments and images from a WordPress export file (WXR)')
            ->addArgument('file', InputArgument::OPTIONAL, 'WordPress export file (Tools > Export in WordPress)')
            ->addOption('job', null, InputOption::VALUE_REQUIRED, 'Run (or continue) the import job with this ID instead of a file')
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Store view IDs for the posts, comma-separated (default: all store views)', '0')
            ->addOption('no-images', null, InputOption::VALUE_NONE, 'Do not download images (they stay linked to the WordPress site)')
            ->addOption('no-redirects', null, InputOption::VALUE_NONE, 'Do not create 301 redirects from the old WordPress addresses')
            ->addOption('no-comments', null, InputOption::VALUE_NONE, 'Do not import comments')
            ->addOption('drafts', null, InputOption::VALUE_NONE, 'Import every post as a draft')
            ->addOption('update', null, InputOption::VALUE_NONE, 'Update posts that were imported before (default: skip them)')
            ->addOption('queue', null, InputOption::VALUE_NONE, 'Only queue the import; cron runs it in the background');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
        } catch (\Exception) {
            // area already set
        }
        $jobId = (int)$input->getOption('job');
        if ($jobId) {
            $job = $this->jobFactory->create();
            $this->jobResource->load($job, $jobId);
            if (!$job->getId()) {
                $output->writeln('<error>Import job ' . $jobId . ' does not exist.</error>');
                return Command::FAILURE;
            }
            if ($job->getData('status') === ImportJob::STATUS_DONE) {
                $output->writeln('Import job ' . $jobId . ' is already finished.');
                return Command::SUCCESS;
            }
            if ($job->getData('status') === ImportJob::STATUS_FAILED) {
                $job->setData('status', ImportJob::STATUS_QUEUED);
                $this->jobResource->save($job);
            }
        } else {
            $file = (string)$input->getArgument('file');
            $path = $file !== '' ? realpath($file) : false;
            if ($path === false || !is_file($path) || !is_readable($path)) {
                $output->writeln('<error>Please give a readable WordPress export file, or --job=ID.</error>');
                return Command::FAILURE;
            }
            $job = $this->jobRunner->queue($path, basename($path), [
                'store_ids' => array_map('intval', explode(',', (string)$input->getOption('store'))),
                'download_images' => !$input->getOption('no-images'),
                'redirects' => !$input->getOption('no-redirects'),
                'comments' => !$input->getOption('no-comments'),
                'as_drafts' => (bool)$input->getOption('drafts'),
                'update_existing' => (bool)$input->getOption('update'),
            ], 'command line');
            $jobId = (int)$job->getId();
            if ($input->getOption('queue')) {
                $output->writeln(sprintf('Queued as import job %d. Cron runs it in the background (Softaware > Blog > WordPress Import shows the progress).', $jobId));
                return Command::SUCCESS;
            }
        }
        $output->writeln(sprintf('Running import job %d ...', $jobId));
        $status = $this->jobRunner->run($jobId, true, static function (string $line) use ($output): void {
            $output->writeln('  ' . $line);
        });
        if ($status === '') {
            $output->writeln('<error>The job is being run by another process right now.</error>');
            return Command::FAILURE;
        }
        $job = $this->jobFactory->create();
        $this->jobResource->load($job, $jobId);
        $stats = $job->getStats();
        $output->writeln(sprintf(
            '%s: %d posts created, %d updated, %d skipped; %d categories, %d tags, %d authors, %d comments, %d images, %d redirects; %d errors.',
            $status === ImportJob::STATUS_DONE ? 'Done' : 'Stopped',
            $stats['posts_created'] ?? 0,
            $stats['posts_updated'] ?? 0,
            $stats['posts_skipped'] ?? 0,
            $stats['categories'] ?? 0,
            $stats['tags'] ?? 0,
            $stats['authors'] ?? 0,
            $stats['comments'] ?? 0,
            $stats['images'] ?? 0,
            $stats['redirects'] ?? 0,
            $stats['errors'] ?? 0
        ));
        return $status === ImportJob::STATUS_DONE ? Command::SUCCESS : Command::FAILURE;
    }
}
