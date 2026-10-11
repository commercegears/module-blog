<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model\Import;

use Psr\Log\LoggerInterface;
use CommerceGears\Blog\Model\Config;
use CommerceGears\Blog\Model\Import\WordPress\Importer;
use CommerceGears\Blog\Model\ImportJob;
use CommerceGears\Blog\Model\ImportJobFactory;
use CommerceGears\Blog\Model\ResourceModel\ImportJob as ImportJobResource;
use CommerceGears\Blog\Model\ResourceModel\ImportJob\CollectionFactory as ImportJobCollectionFactory;

/**
 * Queues and runs WordPress import jobs.
 *
 * Cron (group commercegears_blog, every minute, own process) runs one batch of the oldest queued job per run, about
 * 50 seconds of work, then puts the job back in the queue until it is finished. A job that stopped in the
 * middle (process killed) is queued again after 15 minutes and continues where it stopped.
 */
class JobRunner
{
    public const CRON_SECONDS = 50;
    public const BATCH_POSTS = 200;
    private const STALE_SECONDS = 900;

    public function __construct(
        private readonly ImportJobFactory $jobFactory,
        private readonly ImportJobResource $jobResource,
        private readonly ImportJobCollectionFactory $collectionFactory,
        private readonly Importer $importer,
        private readonly FileStorage $fileStorage,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $options store_ids, download_images, redirects, comments, as_drafts, update_existing
     */
    public function queue(string $fileName, string $originalName, array $options, string $createdBy): ImportJob
    {
        $job = $this->jobFactory->create();
        $job->setData([
            'source' => 'wordpress',
            'file_name' => $fileName,
            'original_name' => mb_substr($originalName, 0, 255),
            'options' => (string)json_encode($this->normaliseOptions($options)),
            'status' => ImportJob::STATUS_QUEUED,
            'stats' => (string)json_encode(['phase' => Importer::PHASE_SCAN, 'position' => 0]),
            'log' => '',
            'created_by' => mb_substr($createdBy, 0, 255),
        ]);
        $this->jobResource->save($job);
        return $job;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function normaliseOptions(array $options): array
    {
        $storeIds = array_values(array_unique(array_map('intval', (array)($options['store_ids'] ?? [0]))));
        if (!$storeIds || in_array(0, $storeIds, true)) {
            $storeIds = [0];
        }
        return [
            'store_ids' => $storeIds,
            'download_images' => !empty($options['download_images']),
            'redirects' => !empty($options['redirects']),
            'comments' => !empty($options['comments']),
            'as_drafts' => !empty($options['as_drafts']),
            'update_existing' => !empty($options['update_existing']),
        ];
    }

    /**
     * Cron: one batch of the oldest queued job.
     *
     * @return int|null ID of the job that was worked on
     */
    public function runNext(): ?int
    {
        if (!$this->config->isFeatureEnabled()) {
            return null;
        }
        $this->requeueStale();
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('status', ImportJob::STATUS_QUEUED)
            ->setOrder('job_id', 'ASC')
            ->setPageSize(1);
        $job = $collection->getFirstItem();
        if (!$job->getId()) {
            return null;
        }
        $this->run((int)$job->getId(), false);
        return (int)$job->getId();
    }

    /**
     * Runs a queued job: one batch, or every batch until it is finished ($untilDone, command line).
     *
     * @param callable(string): void|null $output
     * @return string the job status afterwards; '' when another process is already running it
     */
    public function run(int $jobId, bool $untilDone, ?callable $output = null): string
    {
        if (!$this->jobResource->claim($jobId)) {
            return '';
        }
        $job = $this->jobFactory->create();
        $this->jobResource->load($job, $jobId);
        do {
            try {
                $done = $this->importer->runBatch(
                    $job,
                    $untilDone ? 300 : self::CRON_SECONDS,
                    $untilDone ? 1000 : self::BATCH_POSTS,
                    $output
                );
            } catch (\Throwable $e) {
                $this->logger->error('CommerceGears_Blog import job ' . $jobId . ': ' . $e->getMessage(), ['exception' => $e]);
                $job->setData('log', trim((string)$job->getData('log') . "\n" . gmdate('Y-m-d H:i:s') . ' Import stopped: ' . $e->getMessage()));
                if ($output) {
                    $output('Import stopped: ' . $e->getMessage());
                }
                $this->finish($job, ImportJob::STATUS_FAILED);
                return ImportJob::STATUS_FAILED;
            }
            if ($done) {
                $this->finish($job, ImportJob::STATUS_DONE);
                return ImportJob::STATUS_DONE;
            }
            // keep the claim (status running) between batches on the command line; refresh the start time
            $job->setData('started_at', gmdate('Y-m-d H:i:s'));
            $this->jobResource->save($job);
        } while ($untilDone);

        $job->setData('status', ImportJob::STATUS_QUEUED);
        $this->jobResource->save($job);
        return ImportJob::STATUS_QUEUED;
    }

    public function delete(ImportJob $job): void
    {
        $this->fileStorage->delete((string)$job->getData('file_name'));
        $this->fileStorage->deleteState((int)$job->getId());
        $this->jobResource->delete($job);
    }

    private function finish(ImportJob $job, string $status): void
    {
        $job->setData('status', $status);
        $job->setData('finished_at', gmdate('Y-m-d H:i:s'));
        $this->jobResource->save($job);
        if ($status === ImportJob::STATUS_DONE) {
            $this->fileStorage->delete((string)$job->getData('file_name'));
            $this->fileStorage->deleteState((int)$job->getId());
        }
    }

    private function requeueStale(): void
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('status', ImportJob::STATUS_RUNNING)
            ->addFieldToFilter('started_at', ['lt' => gmdate('Y-m-d H:i:s', time() - self::STALE_SECONDS)]);
        foreach ($collection as $job) {
            $job->setData('status', ImportJob::STATUS_QUEUED);
            $job->setData('log', trim((string)$job->getData('log') . "\n" . gmdate('Y-m-d H:i:s')
                . ' The previous run stopped unexpectedly; continuing where it stopped.'));
            $this->jobResource->save($job);
        }
    }
}
