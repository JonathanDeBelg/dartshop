<?php declare(strict_types=1);

namespace DartshopProduct\Service;

use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\ShopwareException;

class ImageImporter
{
    /**
     * @var MediaService
     */
    protected $mediaService;

    /**
     * @var FileSaver
     */
    private $fileSaver;

    private $mediaRepository;
    private $container;

    /**
     * ImageImport constructor.
     * @param MediaService $mediaService
     * @param FileSaver $fileSaver
     */
    public function __construct(MediaService $mediaService, FileSaver $fileSaver, ContainerInterface $container)
    {
        $this->mediaService = $mediaService;
        $this->fileSaver = $fileSaver;
        $this->container = $container;
        $this->mediaRepository = $this->container->get('media.repository');
    }

    public function addImageToProductMedia($imageUrl, Context $context)
    {
        $mediaId = NULL;
        ($fileExtension = pathinfo($imageUrl, PATHINFO_EXTENSION));
        ($actualFileName = basename($imageUrl, "." . $fileExtension));
        $medias = $this->mediaRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('fileName', $actualFileName)), Context::createDefaultContext())->getIds();
        if(empty($medias)) {
            echo 'image import: ' . $actualFileName;
            $context->disableCache(function (Context $context) use ($imageUrl, &$mediaId, $actualFileName, $fileExtension): void {

                if ($actualFileName && $fileExtension) {
                    $tempFile = tempnam(sys_get_temp_dir(), 'image-import');
                    file_put_contents($tempFile, @file_get_contents($imageUrl));

                    $fileSize = filesize($tempFile);
                    $mimeType = mime_content_type($tempFile);

                    $mediaFile = new MediaFile($tempFile, $mimeType, $fileExtension, $fileSize);
                    $mediaId = $this->mediaService->createMediaInFolder('product', $context, false);
                    echo($actualFileName . "\n");
                    try {
                        $this->fileSaver->persistFileToMedia(
                            $mediaFile,
                            $actualFileName,
                            $mediaId,
                            $context
                        );
                    } catch (ShopwareException $e) {
                        echo $e;
                    }
                }
            }
            );
        } else {
            return $mediaId = $medias[0];
        }
        return $mediaId;
    }
}
