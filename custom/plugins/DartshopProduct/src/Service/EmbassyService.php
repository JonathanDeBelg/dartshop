<?php declare(strict_types=1);

namespace DartshopProduct\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;

class EmbassyService
{
    /**
     * @var Client
     */
    private $restClient;

    public function __construct()
    {
        $this->restClient = new Client();
    }

    /**
     * @return \SimpleXMLElement
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getConfigurableProducts()
    {
        $request = new Request(
            'GET',
            getenv('EMBASSY_HOST') . getenv('EMBASSY_PRODUCT_CONFIG'),
            [
                'Content-Type' => 'application/xml'
            ]
        );

        $response = $this->sendRequest($request);
        return simplexml_load_string($response->getBody()->getContents());
    }

    /**
     * @return \SimpleXMLElement
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getSimpleProducts()
    {
        $request = new Request(
            'GET',
            getenv('EMBASSY_HOST') . getenv('EMBASSY_PRODUCT_SIMPLE'),
            [
                'Content-Type' => 'application/xml'
            ]
        );

        $response = $this->sendRequest($request);
        return simplexml_load_string($response->getBody()->getContents());
    }

    /**
     * @return \SimpleXMLElement
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getProductsWithMedia()
    {
        $request = new Request(
            'GET',
            getenv('EMBASSY_HOST') . getenv('EMBASSY_PRODUCTS'),
            [
                'Content-Type' => 'application/xml'
            ]
        );

        $response = $this->sendRequest($request);
        return simplexml_load_string($response->getBody()->getContents());
    }

    public function getProductsStock() {
        $request = new Request(
            'GET',
            getenv('EMBASSY_HOST') . getenv('EMBASSY_STOCK'),
            [
                'Content-Type' => 'application/xml'
            ]
        );

        $response = $this->sendRequest($request);
        $simpleXMLProducts = simplexml_load_string($response->getBody()->getContents());
        $configProducts = [];

        foreach($simpleXMLProducts as $product) {
            if((string)$product->product_type == 'configurable') {
                array_push($configProducts, $product);
            }
        }
        return $configProducts;
    }

    /**
     * @param Request $request
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws GuzzleException
     */
    private function sendRequest(Request $request)
    {
        try {
            $response = $this->restClient->send($request);
        } catch (GuzzleException $e) {
            $response = $e->getResponse();
            if ($response === null) {
                throw $e;
            }
            $errors = json_decode($response->getBody()->getContents(), true)['errors'];

            $message = "Request failed:\n";

            foreach ($errors as $key => $error) {
                $message .= 'Error ' . $key . ': ' . $error['title'] . ' ' . $error['detail'] . "\n";
            }

            throw new \RuntimeException($message);
        }
        return $response;
    }
}
