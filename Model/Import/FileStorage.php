<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Model\Import;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Math\Random;

/**
 * Uploaded WordPress export files and import progress files in var/softaware_blog/import/ (not reachable from the
 * web). Files are deleted when their import finishes or the job is deleted.
 */
class FileStorage
{
    public const DIR = 'softaware_blog/import';

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly Random $random
    ) {
    }

    /**
     * Moves an uploaded file into the import folder.
     *
     * @return string stored file name
     * @throws LocalizedException
     */
    public function storeUpload(string $tmpPath): string
    {
        $name = $this->random->getRandomString(32, Random::CHARS_LOWERS . Random::CHARS_DIGITS) . '.xml';
        $var = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $var->create(self::DIR);
        $target = $var->getAbsolutePath(self::DIR . '/' . $name);
        if (!is_uploaded_file($tmpPath) || !move_uploaded_file($tmpPath, $target)) {
            throw new LocalizedException(__('The uploaded file could not be stored.'));
        }
        @chmod($target, 0640);
        return $name;
    }

    /**
     * Absolute path of a stored file. Jobs created on the command line keep the absolute path of their file.
     */
    public function getPath(string $fileName): string
    {
        if (str_starts_with($fileName, '/')) {
            return $fileName;
        }
        return $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)
            ->getAbsolutePath(self::DIR . '/' . basename($fileName));
    }

    public function isStored(string $fileName): bool
    {
        return !str_starts_with($fileName, '/');
    }

    public function delete(string $fileName): void
    {
        if ($fileName === '' || !$this->isStored($fileName)) {
            return;
        }
        $var = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $path = self::DIR . '/' . basename($fileName);
        if ($var->isFile($path)) {
            $var->delete($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function readState(int $jobId): array
    {
        $var = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        $path = $this->statePath($jobId);
        if (!$var->isFile($path)) {
            return [];
        }
        $data = json_decode((string)$var->readFile($path), true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    public function writeState(int $jobId, array $state): void
    {
        $var = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $var->writeFile($this->statePath($jobId), (string)json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function deleteState(int $jobId): void
    {
        $var = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        if ($var->isFile($this->statePath($jobId))) {
            $var->delete($this->statePath($jobId));
        }
    }

    private function statePath(int $jobId): string
    {
        return self::DIR . '/job-' . $jobId . '.json';
    }
}
