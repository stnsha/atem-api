<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OctopusApiService
{
    /**
     * ODB API base URL.
     */
    protected string $baseUrl;

    /**
     * ODB API credentials.
     */
    protected string $username;
    protected string $password;

    /**
     * HTTP request timeout in seconds.
     */
    protected int $timeout = 30;

    public function __construct()
    {
        $this->baseUrl = config('services.octopus.api_url') ?? '';
        $this->username = config('credentials.odb_api.username') ?? '';
        $this->password = config('credentials.odb_api.password') ?? '';
    }

    /**
     * Call ODB API using Laravel HTTP client.
     *
     * @param string $method HTTP method (POST, GET, PUT)
     * @param string $endpoint API endpoint path
     * @param array $data Request data
     * @return array Decoded JSON response
     * @throws Exception
     */
    protected function callAPI(string $method, string $endpoint, array $data): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        Log::info('OctopusApiService: API Request', [
            'method' => $method,
            'url' => $url,
            'data_keys' => array_keys($data),
        ]);

        try {
            $http = Http::timeout($this->timeout)
                ->acceptJson()
                ->asJson();

            switch (strtoupper($method)) {
                case 'POST':
                    $response = $http->post($url, $data);
                    break;
                case 'PUT':
                    $response = $http->put($url, $data);
                    break;
                case 'GET':
                default:
                    $response = $http->get($url, $data);
                    break;
            }

            $httpCode = $response->status();
            $body = $response->body();

            Log::info('OctopusApiService: API Response', [
                'method' => $method,
                'url' => $url,
                'http_code' => $httpCode,
                'response_length' => strlen($body),
            ]);

            if ($response->failed()) {
                Log::error('OctopusApiService: API returned error status', [
                    'method' => $method,
                    'url' => $url,
                    'http_code' => $httpCode,
                    'response_body' => substr($body, 0, 500),
                ]);

                throw new Exception("API request failed with status {$httpCode}: " . substr($body, 0, 200));
            }

            $result = $response->json();

            if ($result === null && !empty($body)) {
                Log::error('OctopusApiService: Invalid JSON response', [
                    'response' => substr($body, 0, 500),
                ]);

                throw new Exception('Invalid JSON response from ODB API');
            }

            return $result ?? [];

        } catch (ConnectionException $e) {
            Log::error('OctopusApiService: Connection Failure', [
                'method' => $method,
                'url' => $url,
                'error_message' => $e->getMessage(),
            ]);

            throw new Exception("Connection Failure: " . $e->getMessage(), 0, $e);

        } catch (RequestException $e) {
            Log::error('OctopusApiService: Request Exception', [
                'method' => $method,
                'url' => $url,
                'error_message' => $e->getMessage(),
                'http_code' => $e->response ? $e->response->status() : null,
            ]);

            throw new Exception("Request failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Set custom base URL (useful for testing).
     *
     * @param string $url The base URL
     * @return self
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * Set custom credentials (useful for testing).
     *
     * @param string $username The username
     * @param string $password The password
     * @return self
     */
    public function setCredentials(string $username, string $password): self
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    /**
     * Set HTTP request timeout.
     *
     * @param int $seconds Timeout in seconds
     * @return self
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
}
