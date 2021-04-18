<?php declare(strict_types=1);

namespace DartshopProduct\Command;

use DartshopProduct\Service\EmbassyService;
use DartshopProduct\Service\ImageImporter;
use DartshopProduct\Service\RestService;
use Psr\Container\ContainerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProductsImportCommand extends Command
{
    protected static $defaultName = 'products:import';

    private $restService;
    private $embassyService;
    private $container;
    private $productRepository;
    private $taxRepository;
    private $manufacturerRepository;
    private $imageImport;
    private $mediaRepository;
    /**
     * @var OutputInterface
     */
    private $outputInterface;
    private $categoryRepository;
    private $saleschannelRepository;
    private $propertyRepository;
    private $propertyGroupRepository;
    private $mediaFolderRepository;

    private $productsArray;
    private $productConfigRepository;
    /**
     * @var mixed
     */
    private $taxId;
    /**
     * @var mixed
     */
    private $manufacturerId;
    /**
     * @var mixed
     */
    private $storefrontId;
    private \SimpleXMLElement $otherProducts;
    private \SimpleXMLElement $productsMedia;
    /**
     * @var mixed
     */
    private $productMediaRepository;
    /**
     * @var mixed
     */
    private $orderRepo;

    /**
     * ProductsImportCommand constructor.
     * @param RestService $restService
     * @param EmbassyService $embassyService
     * @param ContainerInterface $container
     * @param ImageImporter $imageImport
     */
    public function __construct(RestService $restService, EmbassyService $embassyService, ContainerInterface $container, ImageImporter $imageImport)
    {
        $this->productsArray = array();
        $this->restService = $restService;
        $this->embassyService = $embassyService;
        $this->container = $container;
        $this->imageImport = $imageImport;
        $this->productRepository = $this->container->get('product.repository');
        $this->productMediaRepository = $this->container->get('product_media.repository');
        $this->taxRepository = $this->container->get('tax.repository');
        $this->mediaRepository = $this->container->get('media.repository');
        $this->mediaFolderRepository = $this->container->get('media_folder.repository');
        $this->manufacturerRepository = $this->container->get('product_manufacturer.repository');
        $this->categoryRepository = $this->container->get('category.repository');
        $this->saleschannelRepository = $this->container->get('sales_channel.repository');
        $this->propertyGroupRepository = $this->container->get('property_group_option.repository');
        $this->propertyRepository = $this->container->get('property_group.repository');
        $this->productConfigRepository = $this->container->get('product_configurator_setting.repository');
        $this->orderRepo = $this->container->get('order.repository');
        $this->taxId = ($this->taxRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', 'Standard rate')), Context::createDefaultContext()))->getIds()[0];
        $this->manufacturerId = ($this->manufacturerRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', 'Dartshop Klumpenaar')), Context::createDefaultContext()))->getIds()[0];
        $this->storefrontId = ($this->saleschannelRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', 'Storefront')), Context::createDefaultContext()))->getIds()[0];

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->outputInterface = $output;
        $output->writeln('Start importing');

//        dd($this->removeAllProducts());
//        dd($this->productRepository->search((new Criteria())->addFilter(new EqualsFilter('productNumber', 'BU-68701')), Context::createDefaultContext()));

//        $this->castDartarrowProducts($productsMedia);

//        $this->productsMedia = $this->embassyService->getConfigurableProducts();
//        $this->otherProducts = $this->embassyService->getSimpleProducts();
//        $this->productImagesSync();
        //        $this->castOtherProducts();
//        $this->createProducts();
        $this->changeMenuOrdering();
        $output->write('Done importing');
        return 0;
    }

    private function changeMenuOrdering() {
        $catPar = ($this->categoryRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', 'Dartshop Klumpenaar')), Context::createDefaultContext()))->getIds()[0];
//        $categoryId = ($this->categoryRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', 'Products')), Context::createDefaultContext()))->getIds()[0];
        $categoryId2 = ($this->categoryRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', 'Privacy')), Context::createDefaultContext()))->getIds()[1];
//        dd($categoryId2);
//        $categoryId3 = ($this->categoryRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', 'Products')), Context::createDefaultContext()))->getIds()[0];
        $cats = ($this->categoryRepository->search((new Criteria()), Context::createDefaultContext()));
      

        $this->categoryRepository->delete([['id' => $categoryId2]], Context::createDefaultContext());
    }

    private function createProducts() {
        $chunks = array_chunk($this->productsArray, 100);

        $counter = 0;
        foreach($chunks as $batch) {
            print_r($batch);
            echo "batchId;" . $counter . "\n";
            $this->productRepository->upsert($batch, Context::createDefaultContext());
            $counter++;
        }
    }

    private function castDartarrowProducts($products)
    {
        $parentId = '';
        $parentCounter = 0;
        $allroundCounter = 0;

        foreach ($products as $product) {
            $castedProduct = $this->castBasicProductStructure($product);

            if (strpos((string)$product->type, 'Dartpijlen')) {
                if ((string)$product->product_type == 'configurable' || ((string)$product->product_type == 'simple' && (string)$product->product_parent_id == '')) {
                    $castedProduct = $this->castParentProductDetailedInfo($castedProduct, $product);
                    $castedProduct = $this->castProductImages($castedProduct);
                    $parentId = $castedProduct['id'];
                    $parentCounter = $allroundCounter;
                } else {
                    if ((string)$product->darts_weight != '') {
                        $castedProduct = $this->castSimpleDartArrowProduct($product, $parentId, $product, $parentCounter);
                    } else {
                        $castedProduct = $this->castParentProductDetailedInfo($castedProduct, $product);
                        $castedProduct = $this->castProductImages($castedProduct);
                    }
                }
            } else {
                continue;
            }

            array_push($this->productsArray, $castedProduct);
            $allroundCounter++;
        }
    }

    private function castOtherProducts() {
        foreach($this->otherProducts as $product) {
            $productId = $this->productRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('productNumber', (string) $product->sku)), Context::createDefaultContext())->getIds();
            $accordingProduct = $this->getAccordingProduct((string)$product->sku);
            if(empty($accordingProduct)) continue;

            if(!strpos((string)$product->type, 'Dartpijlen') && !strpos((string)$accordingProduct->type, 'Dartpijlen')) {
                if(empty($productId)) {
                    if((float)$product->price == round(0, 2)) {
                        continue;
                    } else {
                        $castedProduct = $this->castBasicProductStructure($product);
                        $castedProduct = $this->castParentProductDetailedInfo($castedProduct, $product, true, $accordingProduct);
                        $castedProduct = $this->castProductImages($castedProduct);
                    }
                } else {
                    continue;
                }
            }

            array_push($this->productsArray, $castedProduct);
        }
    }

    private function createProductImages($product): array
    {
        $imageIds = [];
        foreach ($product['images'] as $imageUrl) {
            $ext = pathinfo($imageUrl, PATHINFO_EXTENSION);
            $fileName = basename($imageUrl, "." . $ext);
            $fileName = str_replace(".", "", $fileName);
            if($ext != '') {
                $mediaId = $this->mediaRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('fileName', $fileName)), Context::createDefaultContext())->getIds();
                $mediaId = empty($mediaId) ? $this->imageImport->addImageToProductMedia($imageUrl, Context::createDefaultContext()) : $mediaId[0];
                array_push($imageIds, [
                    "Id" => $product['productNumber'],
                    "mediaId" => $mediaId
                ]);
            }
        }

        return $imageIds;
    }

    private function castProductImages($product)
    {
        $images = $this->createProductImages($product);
        unset($product['images']);
        if (!empty($images)) {
            if (count($images) == 1) {
                $product["coverId"] = $images[0]['mediaId'];
            } else {
                $product["cover"] = ["mediaId" => $images[0]['mediaId']];
                unset($images[0]);
                $product["media"] = $images;
            }
        }

        return $product;
    }

    private function castParentProductDetailedInfo(array $castedProduct, $product, $simpleProduct = false, $accordingProduct = false)
    {
        $categoryName = (!$accordingProduct) ? (string) $product->type : (string) $accordingProduct->type;
        if(empty($categoryName)) dd($castedProduct, $product, $accordingProduct);
        $categoryId = ($this->categoryRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', $categoryName)), Context::createDefaultContext()))->getIds();
        $parentId = Uuid::randomHex();

        if (empty($categoryId)) {
            $categoryParentId = ($this->categoryRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', 'Home')), Context::createDefaultContext()))->getIds()[0];
            $categoryId = Uuid::randomHex();
            $this->categoryRepository->create([[
                'parentId' => $categoryParentId,
                'id' => $categoryId,
                'name' =>$categoryName,
            ]], Context::createDefaultContext());
        } else {
            $categoryId = $categoryId[0];
        }

        $imageArray = ($simpleProduct) ? $this->getProductImagesOfSimpleProduct($product, $accordingProduct) : get_object_vars($product->images->image);

        $medias = [];

        foreach ($imageArray as $image) {
            if (!empty($image)) {
                array_push($medias, $image);
            }
        }

        $castedProduct['id'] = $parentId;
        $castedProduct['categories'] = [['id' => $categoryId]];
        $castedProduct['name'] = ($simpleProduct) ? (string)$product->product_name : (string)$product->name;
        $castedProduct['configuratorSettings'] = array();
        $castedProduct['images'] = $medias;
        return $castedProduct;
    }

    private function castSimpleDartArrowProduct($product, string $parentId, $castedProduct, $counter){
        $propertyGroupId = $this->propertyRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', 'Gewicht')), Context::createDefaultContext())->getIds();
        $optionId = $this->propertyGroupRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', (string)$product->darts_weight)), Context::createDefaultContext())->getIds();
        $simpleProductId = Uuid::randomHex();
        $propertyGroupId = $this->getProperty($propertyGroupId);
        $optionArray = $this->getOptionArray($product, $propertyGroupId, $optionId);

        $castedProduct = [
            'id' => $simpleProductId,
            'stock' => (int)$product->qty,
            'productNumber' => (string)$product->sku,
            'parentId' => $parentId,
            'options' => [$optionArray],
        ];

        if(empty($this->productsArray[$counter]['configuratorSettings'])) $this->productsArray[$counter]['configuratorSettings'] = array();

        array_push($this->productsArray[$counter]['configuratorSettings'], [
            'id' => $simpleProductId,
            'option' => $optionArray,
        ]);

        if($this->productsArray[$counter]['price'][0]['gross'] == round(0, 2)) {
            $castedProduct['price'] = [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => (float)$product->price,
                'net' => ((float)$product->price * 0.79),
                'linked' => false
            ]];
        }

        return $castedProduct;
    }

    private function getProperty($propertyGroupId): string
    {
        if (empty($propertyGroupId)) {
            $propertyGroupId = Uuid::randomHex();

            $this->propertyRepository->create([
                [
                    'id' => $propertyGroupId,
                    'name' => 'Gewicht',
                ]
            ], Context::createDefaultContext());

            return $propertyGroupId;
        }

        $propertyGroupId = $propertyGroupId[0];
        return $propertyGroupId;
    }

    private function getOptionArray($product, string $propertyGroupId, $optionId): array
    {
        if (empty($optionId)) {
            $optionId = Uuid::randomHex();
            $optionArray = [
                'id' => $optionId,
                'name' => (string)$product->darts_weight,
                'groupId' => $propertyGroupId,
            ];
            $this->propertyGroupRepository->create([$optionArray], Context::createDefaultContext());

            return $optionArray;
        }
        return [
            'id' => $optionId[0],
            'name' => (string)$product->darts_weight,
            'groupId' => $propertyGroupId,
        ];
    }

    private function removeAllProductMedia() {
        $test = $this->mediaFolderRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', 'Product Media')), Context::createDefaultContext())->getIds()[0];
        $medias = $this->mediaRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('mediaFolderId', $test)), Context::createDefaultContext())->getIds();
        $timed = array();

        foreach($medias as $media) {
            array_push($timed, [
                'id' => $media
            ]);
        }

        $timed = array_chunk($timed, 100);

        $counter = 0;
        foreach($timed as $batch) {
            echo "batchId;" . $counter . "\n";
            $this->mediaRepository->delete($batch, Context::createDefaultContext());
            $counter++;
        }
    }

    private function removeAllProducts() {
        $test = $this->productRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();
        $timed = array();

        foreach($test as $product) {
            array_push($timed, [
                'id' => $product
            ]);
        }

        $timed = array_chunk($timed, 100);

        $counter = 0;
        foreach($timed as $batch) {
            echo "batchId;" . $counter . "\n";
            $this->productRepository->delete($batch, Context::createDefaultContext());
            $counter++;
        }
    }

    /**
     * @param $product
     * @return array
     */
    private function castBasicProductStructure($product): array
    {
        return [
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => (float)$product->price,
                'net' => ((float)$product->price * 0.79),
                'linked' => false
            ]],
            'manufacturer' => ['id' => $this->manufacturerId],
            'tax' => ['id' => $this->taxId],
            'stock' => (int)$product->qty,
            'productNumber' => (string)$product->sku,
            'ean' => (string)$product->ean,
            'visibilities' => [
                [
                    'salesChannelId' => $this->storefrontId,
                    'visibility' => 30
                ]
            ]
        ];
    }

    private function getAccordingProduct(string $productSku)
    {
        foreach ($this->productsMedia as $element) {
            if ($productSku == (string)$element->sku) {
                return $element;
            }
        }

        return false;
    }

    private function getAccordingProductMedia(string $productParentId)
    {
        foreach ($this->productsMedia as $element) {
            if ($productParentId == (string)$element->product_id) {
                return get_object_vars($element->images->image);
            }
        }

        return [];
    }

    private function getProductImagesOfSimpleProduct($product, $accordingProduct)
    {
        $imagesFromProduct = get_object_vars($product->images->image);
        if(empty($imagesFromProduct)) {
            return $this->getAccordingProductMedia((string)$accordingProduct->product_parent_id);
        }

        return $imagesFromProduct;
    }

    private function productImagesSync() {
        foreach($this->otherProducts as $product) {
            $accordingProduct = $this->getAccordingProduct((string)$product->sku);
            if(empty($accordingProduct)) continue;


            if(!strpos((string)$product->type, 'Dartpijlen') && !strpos((string)$accordingProduct->type, 'Dartpijlen')) {
                $productEntity = $this->productRepository->search((new Criteria())->addFilter(new EqualsFilter('productNumber', (string) $product->sku)), Context::createDefaultContext())->getEntities()->first();
                $productMediaEntity = $this->productMediaRepository->search((new Criteria())->addFilter(new EqualsFilter('productId', $productEntity->getId())), Context::createDefaultContext())->getEntities()->first();

                if(empty($productMediaEntity)) {
                    $images = [];
                    if ($product->images->count() == 0) {
                        if ($accordingProduct->images->count() == 0) {
                            if((string)$product->sku == 'TA-129893') dd($accordingProduct);
                            if (!empty((string)$accordingProduct->product_parent_id)) {

                                $images = $this->getAccordingProductMedia((string)$accordingProduct->product_parent_id);
                            } else {
                                echo 'Geen afbeelding gevonden voor; ' . (string)$product->sku;
                                continue;
                            }
                        } else {
                            $images = get_object_vars($accordingProduct->images->image);
                        }
                    } else {
                        $images = get_object_vars($product->images->image);
                    }

                    $productUpsert = [];
                    $productUpsert['id'] = $productEntity->getId();
                    $mediaCounter = 1;

                    foreach ($images as $imageUrl) {
                        ($fileExtension = pathinfo($imageUrl, PATHINFO_EXTENSION));
                        ($actualFileName = basename($imageUrl, "." . $fileExtension));
                        $mediaId = $this->mediaRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('fileName', $actualFileName)), Context::createDefaultContext())->getIds()[0];

                        if (empty($mediaId)) {
                            echo 'Niets gevonden voor; ' . (string)$product->sku;
                            continue;
                        }

                        if ($mediaCounter == 1) {
                            $productUpsert['cover'] = ['mediaId' => $mediaId];
                        } else {
                            array_push($productUpsert['images'], ['Id' => $productEntity->getId(), 'mediaId' => $mediaId]);
                        }
                        $mediaCounter++;
                    }

                    array_push($this->productsArray, $productUpsert);
                }
            }
        }
    }
}
