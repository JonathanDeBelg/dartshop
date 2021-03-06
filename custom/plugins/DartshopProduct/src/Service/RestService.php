<?php declare(strict_types=1);

namespace DartshopProduct\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class RestService
{
    /**
     * @var Client
     */
    private $restClient;

    /**
     * @var SystemConfigService
     */
    private $config;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var string
     */
    private $refreshToken;

    /**
     * @var \DateTimeInterface
     */
    private $expiresAt;

    public function __construct(SystemConfigService $config)
    {
        $this->restClient = new Client();
        $this->config = $config;
    }

    /**
     * Entry point for any API call
     * @param string $method
     * @param string $uri
     * @param array|null $body
     * @return ResponseInterface
     * @throws \Exception
     */
    public function request(string $method, string $uri, ?array $body = null): ResponseInterface
    {
        if ($this->accessToken === null || $this->refreshToken === null || $this->expiresAt === null) {
            $this->getAdminAccess();
        }

        $bodyEncoded = json_encode($body);

        $request = $this->createShopwareApiRequest($method, $uri, $bodyEncoded);

        return $this->send($request, $uri);
    }

    /**
     * Send a given request and refresh your access key if needed
     * @param RequestInterface $request
     * @param string $uri
     * @return ResponseInterface
     * @throws \Exception
     */
    private function send(RequestInterface $request, string $uri): ResponseInterface
    {
        if ($this->expiresAt <= (new \DateTime())) {
            $this->refreshAuthToken();

            $body = $request->getBody()->getContents();

            $request = $this->createShopwareApiRequest($request->getMethod(), $uri, $body);
        }

        return $this->sendRequest($request);
    }

    /**
     * Create a Request object for guzzle, especially for the Shopware Platform API
     * @param string $method
     * @param string $uri
     * @param string|null $body
     * @return RequestInterface
     */
    private function createShopwareApiRequest(string $method, string $uri, ?string $body = null): RequestInterface
    {
        return new Request(
            $method,
            getenv('APP_URL') . '/api/v' . PlatformRequest::API_VERSION .'/' . $uri,
            [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => '*/*',
                'Content-Type' => 'application/json'
            ],
            $body
        );
    }

    /**
     * Request authorization information for future requests
     * @throws GuzzleException
     */
    private function getAdminAccess(): void
    {
        $body = \json_encode([
            'client_id' => 'administration',
            'grant_type' => 'password',
            'scopes' => $this->config->get('DartshopProduct.config.scope'),
            'username' => $this->config->get('DartshopProduct.config.username'),
            'password' => $this->config->get('DartshopProduct.config.password')
        ]);

        $request = new Request(
            'POST',
            getenv('APP_URL') . '/api/oauth/token',
            ['Content-Type' => 'application/json'],
            $body
        );

        $response = $this->sendRequest($request);

        $body = json_decode($response->getBody()->getContents(), true);

        $this->setAccessData($body);
    }

    /**
     * Request updated authorization information, since your past access key might be expired
     */
    private function refreshAuthToken(): void
    {
        $body = \json_encode([
            'client_id' => 'administration',
            'grant_type' => 'refresh_token',
            'scopes' => $this->config->get('RestApiHandling.config.scope'),
            'refresh_token' => $this->refreshToken
        ]);

        $request = new Request(
            'POST',
            getenv('APP_URL') . '/api/oauth/token',
            ['Content-Type' => 'application/json'],
            $body
        );

        $response = $this->sendRequest($request);

        $body = json_decode($response->getBody()->getContents(), true);

        $this->setAccessData($body);
    }

    /**
     * Set the authorization information to its corresponding properties
     * @param array $body
     */
    private function setAccessData(array $body): void
    {
        $this->accessToken = $body['access_token'];
        $this->refreshToken = $body['refresh_token'];
        $this->expiresAt = $this->calculateExpiryTime((int) $body['expires_in']);
    }

    /**
     * Generate a DateTimeInterface object to determine whether the request would fail due to expired authorization
     * @param int $expiresIn
     * @return \DateTimeInterface
     * @throws \Exception
     */
    private function calculateExpiryTime(int $expiresIn): \DateTimeInterface
    {
        $expiryTimestamp = (new \DateTime())->getTimestamp() + $expiresIn;

        return (new \DateTimeImmutable())->setTimestamp($expiryTimestamp);
    }

    private function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->restClient->send($request);
        } catch (ClientException|ConnectException|RequestException|GuzzleException $e) {
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
