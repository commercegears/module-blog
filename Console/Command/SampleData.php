<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\State as AppState;
use CommerceGears\Blog\Model\SampleData as SampleDataModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Adds (or removes) a few sample posts, categories, tags and an author.
 */
class SampleData extends Command
{
    public function __construct(
        private readonly SampleDataModel $sampleData,
        private readonly AppState $appState,
        private readonly CacheManager $cacheManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('commercegears:blog:sample-data')
            ->setDescription('Add sample blog posts, categories, tags and an author (or remove them with --remove)')
            ->addOption('remove', null, InputOption::VALUE_NONE, 'Remove the sample content');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception) {
            // area already set
        }
        if ($input->getOption('remove')) {
            $result = $this->sampleData->remove();
            $output->writeln(sprintf(
                'Removed the sample content: %d posts, %d categories, %d tags, %d authors, %d comments.',
                $result['posts'],
                $result['categories'],
                $result['tags'],
                $result['authors'],
                $result['comments']
            ));
        } else {
            if ($this->sampleData->exists()) {
                $output->writeln('The sample content is already there. Remove it first with --remove to add it again.');
                return Command::SUCCESS;
            }
            $result = $this->sampleData->install();
            $output->writeln(sprintf(
                'Added %d sample posts, %d categories, %d tags, %d author and %d comment. Remove them with --remove.',
                $result['posts'],
                $result['categories'],
                $result['tags'],
                $result['authors'],
                $result['comments']
            ));
        }
        $this->cacheManager->clean(['full_page', 'block_html']);
        return Command::SUCCESS;
    }
}
