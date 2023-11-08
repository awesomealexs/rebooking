<?php

namespace App\RatehawkApi;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Utils;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class RatehawkApi
{
    /**
     * @var HttpClient
     */
    private HttpClient $httpClient;

    private Logger $logger;

    private string $downLoadDirectory;

    /**
     * @param string $key
     * @param array $config Guzzle http client default request options
     * @throws InvalidAuthData
     *
     * @see \GuzzleHttp\RequestOptions for a list of available request options.
     */
    function __construct(string $key, string $downLoadDirectory, array $config = [])
    {
        $logDir = dirname(__DIR__) . '/Logs/ApiLog.log';
        $handler = new RotatingFileHandler($logDir);

        $this->logger = new Logger('', [], [], new \DateTimeZone('Europe/Moscow'));
        $this->logger->pushHandler($handler);

        $this->downLoadDirectory = $downLoadDirectory;

        $config = RatehawkApi::_add_auth($config, getenv('RATEHAWK_KEY_ID'), getenv('RATEHAWK_API_KEY'));
        $config = RatehawkApi::_add_user_agent($config);
        $this->httpClient = new HttpClient($config);
    }

    /**
     * @param array $config
     * @param string $key
     * @return array
     */
    private static function _add_auth(array $config, string $keyId, string $apiKey): array
    {
        $config[RequestOptions::AUTH] = [$keyId, $apiKey];
        return $config;
    }

    /**
     * @param array $config
     * @return array
     */
    private static function _add_user_agent(array $config): array
    {
        $papiSdkVersion = Constants::NAME . '/' . Constants::VERSION;
        $httpClientVersion = Utils::defaultUserAgent();
        $phpVersion = 'php/' . PHP_VERSION;
        $headers = ['User-Agent' => $papiSdkVersion . ' ' . $httpClientVersion . ' (' . $phpVersion . ')'];

        if (!isset($config[RequestOptions::HEADERS])) {
            $config[RequestOptions::HEADERS] = $headers;
        } else {
            $config[RequestOptions::HEADERS] += $headers;
        }

        return $config;
    }

    public function getRegionDump(): string
    {
        return $this->getAndSaveFile(Endpoints::HOTEL_REGION_DUMP);
    }

    protected function getAndSaveFile(string $endpoint, array $options = []): string
    {
        $response = json_decode($this->httpClient
            ->post($endpoint, $options)
            ->getBody()
            ->getContents(),
            true,
            512,
            JSON_THROW_ON_ERROR);

        if (empty($response['data']['url'])) {
            throw new \Exception('EMPTY HOTELS DUMP URL');
        }

        $tempFileName = $this->downLoadDirectory . DIRECTORY_SEPARATOR . md5(microtime(true)) . '.zstd';

        $tempFile = fopen($tempFileName, 'wb');

        $httpClientWOBasic = new HttpClient();

        $httpClientWOBasic->get(
            $response['data']['url'],
            [
                RequestOptions::SINK => $tempFile,
            ]
        );

        return $tempFileName;
    }

    public function getHotelsDump(): string
    {
        $options['body'] = json_encode([
            'inventory' => 'all',
            'language' => 'ru',
        ], JSON_THROW_ON_ERROR);

        return $this->getAndSaveFile(Endpoints::HOTEL_INFO_DUMP, $options);
    }
}
