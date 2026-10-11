<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Block\Adminhtml\Import;

use Magento\Backend\Block\Template;
use Softaware\Blog\Model\ImportJob;
use Softaware\Blog\Model\Source\ImportStatus;

/**
 * Details, counts and log of an import job.
 */
class View extends Template
{
    protected $_template = 'Softaware_Blog::import/view.phtml';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly ImportStatus $importStatus,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getJob(): ?ImportJob
    {
        $job = $this->getData('job');
        return $job instanceof ImportJob ? $job : null;
    }

    public function getStatusLabel(string $status): string
    {
        foreach ($this->importStatus->toOptionArray() as $option) {
            if ($option['value'] === $status) {
                return (string)$option['label'];
            }
        }
        return $status;
    }

    /**
     * @return array<string, int>
     */
    public function getCounts(): array
    {
        $stats = $this->getJob()?->getStats() ?? [];
        $labels = [
            'posts_total' => __('Posts in the file'),
            'posts_created' => __('Posts created'),
            'posts_updated' => __('Posts updated'),
            'posts_skipped' => __('Posts skipped (imported before)'),
            'categories' => __('Categories created'),
            'tags' => __('Tags created'),
            'authors' => __('Authors created'),
            'comments' => __('Comments'),
            'images' => __('Images downloaded'),
            'image_errors' => __('Images not downloaded'),
            'redirects' => __('Redirects created'),
            'errors' => __('Errors'),
        ];
        $counts = [];
        foreach ($labels as $key => $label) {
            $counts[(string)$label] = (int)($stats[$key] ?? 0);
        }
        return $counts;
    }

    /**
     * @return array<string, string>
     */
    public function getOptionLabels(): array
    {
        $options = $this->getJob()?->getOptions() ?? [];
        $yesNo = static fn ($value) => (string)($value ? __('Yes') : __('No'));
        return [
            (string)__('Download images') => $yesNo($options['download_images'] ?? false),
            (string)__('Redirects from old addresses') => $yesNo($options['redirects'] ?? false),
            (string)__('Comments') => $yesNo($options['comments'] ?? false),
            (string)__('Import as drafts') => $yesNo($options['as_drafts'] ?? false),
            (string)__('Update posts imported before') => $yesNo($options['update_existing'] ?? false),
        ];
    }

    public function formatUtc(?string $value): string
    {
        if (!$value) {
            return '';
        }
        return $this->formatDate(new \DateTime($value, new \DateTimeZone('UTC')), \IntlDateFormatter::MEDIUM, true);
    }
}
