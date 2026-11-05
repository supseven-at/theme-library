<?php

declare(strict_types=1);

namespace Supseven\ThemeLibrary\ViewHelpers;

use Supseven\ThemeLibrary\Imaging\ImageGeneratorService;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Extbase\Domain\Model\FileReference as ExtbaseReference;

/**
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class ImageUriViewHelper extends \TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper
{
    public function __construct(
        protected ImageGeneratorService $generatorService,
    ) {
    }

    public function initializeArguments(): void
    {
        $this->registerArgument('image', 'object', 'image');
        $this->registerArgument('width', 'int', 'width of the image. This can be a numeric value representing the fixed width of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.', false, 0);
        $this->registerArgument('height', 'int', 'height of the image. This can be a numeric value representing the fixed height of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.', false, 0);
        $this->registerArgument('cropVariant', 'string', 'select a cropping variant, in case multiple croppings have been specified or stored in FileReference', false, 'default');
        $this->registerArgument('fileExtension', 'string', 'Custom file extension to use', false, 'webp');
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
