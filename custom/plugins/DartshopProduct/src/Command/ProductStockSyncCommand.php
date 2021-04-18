<?php declare(strict_types=1);

namespace DartshopProduct\Command;

use DartshopProduct\Service\EmbassyService;
use DartshopProduct\Service\RestService;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProductStockSyncCommand extends Command
{
    protected static $defaultName = 'products:stock:sync';

    private $restService;
    private $embassyService;
    private $container;
    private $productRepository;
    private $outputInterface;
    private $productsArray;

    /**
     * ProductsImportCommand constructor.
     * @param RestService $restService
     * @param EmbassyService $embassyService
     * @param ContainerInterface $container
     */
    public function __construct(RestService $restService, EmbassyService $embassyService, ContainerInterface $container)
    {
        $this->productsArray = array();
        $this->restService = $restService;
        $this->embassyService = $embassyService;
        $this->container = $container;
        $this->productRepository = $this->container->get('product.repository');

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->outputInterface = $output;
        $output->writeln('Start stock sync');

        $products = $this->embassyService->getProductsStock();
        $stocks = $this->castStocks($products);
        $this->updateStocks($stocks);

        $output->write('Done syncing stock');
        return 0;
    }

    private function castStocks($products)
    {
        $updatedStocksArray = array();
        foreach($products as $product) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productNumber', (string)$product->sku));

            $productId = $this->productRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

            array_push($updatedStocksArray, [
                'id' => $productId,
                'stock' => (int)$product->qty
            ]);
        }

        return $updatedStocksArray;
    }

    private function updateStocks(array $stocks)
    {
        $chunks = array_chunk($stocks, 100);

        $counter = 0;
        foreach($chunks as $batch) {
            echo "batchId;" . $counter . "\n";
            $this->productRepository->update($batch, Context::createDefaultContext());
            $counter++;
        }
    }
}
