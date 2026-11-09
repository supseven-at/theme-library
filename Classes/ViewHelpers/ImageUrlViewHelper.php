<?php

declare(strict_types=1);

namespace Supseven\ThemeLibrary\ViewHelpers;

use Supseven\ThemeLibrary\Imaging\ImageGeneratorService;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Extbase\Domain\Model\FileReference as ExtbaseReference;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class ImageUrlViewHelper extends AbstractViewHelper
{
    public function __construct(
        protected ImageGeneratorService $generatorService,
    ) {
    }

    public function initializeArguments(): void
    {
        $this->registerArgument('image', 'object', 'image to create a URL for. Can be a FAL file, a FAL reference or an extbase FileReference. Must be an image.');
        $this->registerArgument('width', 'int', 'maximum width of the image. The default of `0` means no scaling.', false, 0);
        $this->registerArgument('height', 'int', 'maximum height of the image. The default of `0` means no scaling', false, 0);
        $this->registerArgument('cropVariant', 'string', 'select a cropping variant, in case multiple croppings have been specified or stored in FileReference', false, 'default');
        $this->registerArgument('fileExtension', 'string', 'file extension to use', false, 'webp');
    }

    public function render(): string
    {
        $image = $this->arguments['image'];

        if ($image instanceof ExtbaseReference) {
            $image = $image->getOriginalResource();
        }

        if (!$image instanceof FileReference && !$image instanceof File) {
            throw new \InvalidArgumentException('Image must be an instance of FileReference or File');
        }

        return $this->generatorService->generateUrl(
            $image,
            $this->arguments['width'],
            $this->arguments['height'],
            $this->arguments['cropVariant'],
            $this->arguments['fileExtension'],
        );
    }
}
