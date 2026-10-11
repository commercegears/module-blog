<?php
/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\Blog\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Stores blog images in pub/media/commercegears_blog/<folder>/<year>/<month>/ and returns the path relative to
 * pub/media/commercegears_blog/.
 */
class ImageUploader
{
    public const BASE_PATH = 'commercegears_blog';
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png', 'webp'];
    public const MAX_BYTES = 10485760;

    public function __construct(
        private readonly UploaderFactory $uploaderFactory,
        private readonly AdapterFactory $imageAdapterFactory,
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Saves an uploaded file (admin image field).
     *
     * @return array{name: string, file: string, url: string, size: int, type: string}
     * @throws LocalizedException
     */
    public function upload(string $fileId, string $folder): array
    {
        $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        $uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        $uploader->addValidateCallback('commercegears_blog_image', $this->imageAdapterFactory->create(), 'validateUploadFile');
        $relativeDir = $this->getRelativeDir($folder);
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $result = $uploader->save($media->getAbsolutePath(self::BASE_PATH . '/' . $relativeDir));
        if (!$result || empty($result['file'])) {
            throw new LocalizedException(__('The image could not be saved.'));
        }
        $file = $relativeDir . '/' . ltrim((string)$result['file'], '/');
        return [
            'name' => (string)$result['file'],
            'file' => $file,
            'url' => $this->getUrl($file),
            'size' => (int)($result['size'] ?? 0),
            'type' => (string)($result['type'] ?? 'image'),
        ];
    }

    /**
     * Saves downloaded image bytes (WordPress import).
     *
     * @throws LocalizedException when the bytes are not a supported image
     */
    public function saveContent(string $content, string $fileName, string $folder): string
    {
        if ($content === '' || strlen($content) > self::MAX_BYTES) {
            throw new LocalizedException(__('The image is empty or larger than 10 MB.'));
        }
        $info = @getimagesizefromstring($content);
        $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($extensions[$info[2]])) {
            throw new LocalizedException(__('The file is not a JPEG, PNG, GIF or WebP image.'));
        }
        $base = strtolower((string)preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo($fileName, PATHINFO_FILENAME)));
        $base = trim($base, '-') ?: 'image';
        $base = substr($base, 0, 80);
        $relativeDir = $this->getRelativeDir($folder);
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $name = $base . '.' . $extensions[$info[2]];
        $counter = 1;
        while ($media->isExist(self::BASE_PATH . '/' . $relativeDir . '/' . $name)) {
            $name = $base . '-' . $counter++ . '.' . $extensions[$info[2]];
        }
        $media->writeFile(self::BASE_PATH . '/' . $relativeDir . '/' . $name, $content);
        return $relativeDir . '/' . $name;
    }

    /**
     * A path starting with "/" is relative to pub/media/, anything else to pub/media/commercegears_blog/.
     */
    public function getUrl(string $file): string
    {
        $media = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        return str_starts_with($file, '/') ? $media . ltrim($file, '/') : $media . self::BASE_PATH . '/' . $file;
    }

    /**
     * Value for a UI image field.
     *
     * @return array<int, array{name: string, file: string, url: string, size: int, type: string}>
     */
    public function toUiValue(string $file): array
    {
        if ($file === '') {
            return [];
        }
        $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $path = str_starts_with($file, '/') ? ltrim($file, '/') : self::BASE_PATH . '/' . $file;
        $size = 0;
        try {
            $size = $media->isExist($path) ? (int)($media->stat($path)['size'] ?? 0) : 0;
        } catch (\Exception) {
            $size = 0;
        }
        return [[
            'name' => basename($file),
            'file' => $file,
            'url' => $this->getUrl($file),
            'size' => $size,
            'type' => 'image',
        ]];
    }

    /**
     * Stored path from a posted UI image field value.
     */
    public function fromUiValue(mixed $value): ?string
    {
        if (!is_array($value) || !isset($value[0]) || !is_array($value[0])) {
            return null;
        }
        $file = (string)($value[0]['file'] ?? '');
        $url = (string)($value[0]['url'] ?? '');
        $marker = '/' . self::BASE_PATH . '/';
        if ($url !== '' && ($file === '' || !str_contains($url, $marker))) {
            // Picked from the media gallery: a path below pub/media/commercegears_blog/, or "/<path>" below pub/media/.
            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            if (str_contains($url, $marker)) {
                $file = substr($url, strpos($url, $marker) + strlen($marker));
            } elseif (str_starts_with($url, $mediaUrl)) {
                $file = '/' . substr($url, strlen($mediaUrl));
            } elseif (($position = strpos($url, '/media/')) !== false) {
                $file = '/' . substr($url, $position + 7);
            }
        }
        $file = str_replace(['..', '\\'], '', $file);
        return trim($file, '/') !== '' ? $file : null;
    }

    private function getRelativeDir(string $folder): string
    {
        $folder = preg_replace('/[^a-z0-9_-]/', '', strtolower($folder)) ?: 'post';
        return $folder . '/' . date('Y') . '/' . date('m');
    }
}
