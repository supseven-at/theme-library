<?php

declare(strict_types=1);

namespace Supseven\ThemeLibrary\Imaging;

use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Tuupola\Base62;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class ImageGeneratorService
{
    protected const string TaskType = 'Image.CropScaleMask';

    protected Base62 $base62;

    public function __construct(
        protected ResourceFactory $resourceFactory,
        protected ConnectionPool $connectionPool,
        protected Context $context,
    ) {
        $this->base62 = new Base62();
    }

    public function generateUrl(File|FileReference $image, int $width, int $height, string $cropVariant, string $ext): string
    {
        if (FileType::tryFrom($image->getType()) !== FileType::IMAGE
            || !in_array(strtolower($image->getExtension()), ['jpg', 'jpeg', 'gif', 'png', 'avif', 'webp'])) {
            throw new \InvalidArgumentException('Invalid file format of given image');
        }

        if (!in_array($ext, ['jpg', 'jpeg', 'gif', 'png', 'avif', 'webp'])) {
            throw new \InvalidArgumentException('Invalid target image format');
        }

        if ($image->getStorage()->getDriverType() !== 'Local') {
            throw new \InvalidArgumentException('Only local FAL storages are supported');
        }

        $cropString = $image->getProperty('crop');

        if (is_array($cropString)) {
            $cropString = json_encode($cropString);
        }

        $type = $image instanceof FileReference ? 'ref' : 'file';
        $prefix = $type . '-' . $image->getUid();

        $processingProperties = [
            $width,
            $height,
        ];

        $cropVariantCollection = CropVariantCollection::create((string)$cropString);
        $cropVariant = $cropVariant ?: 'default';
        $cropArea = $cropVariantCollection->getCropArea($cropVariant);

        if (!$cropArea->isEmpty()) {
            $crop = $cropArea->makeAbsoluteBasedOnFile($image);

            $processingProperties = [
                ...$processingProperties,
                (int)$crop->getWidth(),
                (int)$crop->getHeight(),
                (int)$crop->getOffsetTop(),
                (int)$crop->getOffsetLeft(),
            ];
        }

        $storageProcessed = $image->getStorage()->getProcessingFolder()->getPublicUrl();

        if (!str_starts_with($storageProcessed, '/') && !str_contains($storageProcessed, '://')) {
            $storageProcessed = '/' . $storageProcessed;
        }

        $processing = implode('|', array_map(dechex(...), $processingProperties));
        $validationKey = $this->generateValidationHash($prefix, $processing, $ext);

        return rtrim($storageProcessed, '/')
            . '/' . $prefix
            . '/' . $this->base62->encode($processing)
            . '/' . $this->base62->encode($validationKey)
            . '.' . $ext;
    }

    public function generateImage(string $targetFile, string $type, int $uid, string $processing, string $key, string $ext): EncodedImageInterface
    {
        $processing = $this->base62->decode($processing);
        $givenKey = $this->base62->decode($key);
        $calculatedKey = $this->generateValidationHash($type . '-' . $uid, $processing, $ext);

        if (!hash_equals($calculatedKey, $givenKey)) {
            throw new \InvalidArgumentException('Invalid processing key');
        }

        if ($type === 'ref') {
            $source = $this->resourceFactory->getFileReferenceObject($uid);
        } else {
            $source = $this->resourceFactory->getFileObject($uid);
        }

        if (!$source instanceof File && !$source instanceof FileReference) {
            throw new \InvalidArgumentException('Invalid file object UID');
        }

        $processing = array_map(hexdec(...), explode('|', $processing));

        [$width, $height, $cropWidth, $cropHeight, $cropTop, $cropLeft] = array_pad($processing, 6, 0);
        $image = $this->loadFile($source);
        $image->removeProfile();

        if ($cropWidth && $cropHeight) {
            $image->crop($cropWidth, $cropHeight, $cropLeft, $cropTop);
        }

        if ($width || $height) {
            $width = (int)array_shift($processing);
            $height = (int)array_shift($processing);

            if (!$height) {
                $image->scaleDown(width: $width);
            } elseif (!$width) {
                $image->scaleDown(height: $height);
            } else {
                $image->scaleDown($width, $height);
            }
        }

        $quality = (int)($GLOBALS['TYPO3_CONF_VARS']['GFX'][$ext . '_quality'] ?? -2);
        $speed = (int)($GLOBALS['TYPO3_CONF_VARS']['GFX'][$ext . '_speed'] ?? 5);

        if ($speed < 0 || $speed > 10) {
            $speed = -1;
        }

        if ($quality < -1 || $quality > 100) {
            $quality = match ($ext) {
                'jpg', 'jpeg' => 96,
                'avif'        => 67,
                default       => 75,
            };
        }

        $result = match ($ext) {
            'jpg', 'jpeg' => $image->encodeUsingFormat(Format::JPEG, quality: $quality, progressive: true),
            'png'         => $image->encodeUsingFormat(Format::PNG, compression: 9),
            'gif'         => $image->encodeUsingFormat(Format::GIF),
            'avif'        => $image->encodeUsingFormat(Format::AVIF, quality: $quality, speed: $speed),
            default       => $image->encodeUsingFormat(Format::WEBP, quality: $quality),
        };

        $original = $source;

        if ($original instanceof FileReference) {
            $original = $original->getOriginalFile();
        }

        $processingFolder = $original->getStorage()->getProcessingFolder();
        $parts = explode(dirname($processingFolder->getPublicUrl()), $targetFile);

        if (count($parts) === 2) {
            $identifier = '/' . ltrim((string)array_pop($parts), '/');

            if ($identifier !== '/') {
                $config = serialize(compact('width', 'height', 'cropWidth', 'cropHeight', 'cropTop', 'cropLeft'));
                $name = basename($identifier);
                $checksumData = [
                    $original->getUid(),
                    self::TaskType . '.' . $name . $original->getModificationTime(),
                    $config,
                ];
                $checkSum = substr(hash('md5', implode('|', $checksumData)), 0, 10);
                $tstamp = $this->context->getPropertyFromAspect('date', 'timestamp');

                $this->connectionPool->getConnectionForTable('sys_file_processedfile')->insert(
                    'sys_file_processedfile',
                    [
                        'tstamp'            => $tstamp,
                        'crdate'            => $tstamp,
                        'storage'           => $original->getStorage()->getUid(),
                        'original'          => $original->getUid(),
                        'identifier'        => $identifier,
                        'name'              => $name,
                        'configuration'     => $config,
                        'originalfilesha1'  => $original->getSha1(),
                        'task_type'         => self::TaskType,
                        'checksum'          => $checkSum,
                        'width'             => $image->width(),
                        'height'            => $image->height(),
                        'configurationsha1' => hash('sha1', $checkSum),
                    ]
                );
            }
        }

        return $result;
    }

    protected function loadFile(FileReference|File $fileReference): ImageInterface
    {
        $manager = ImageManager::usingDriver(ImagickDriver::class);

        return $manager->decodeBinary($fileReference->getContents());
    }

    protected function generateValidationHash(string $prefix, string $processing, string $ext): string
    {
        $encryptionKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];

        return hash_hmac('sha256', $prefix . $processing . $ext, $encryptionKey, true);
    }
}
