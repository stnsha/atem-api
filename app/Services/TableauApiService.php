<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TableauApiService
{
    /**
     * Tableau Server connection details.
     */
    protected string $baseUrl;
    protected string $apiVersion;
    protected string $siteId;
    protected string $viewUrl;

    /**
     * Tableau credentials.
     */
    protected string $username;
    protected string $password;

    /**
     * Cached X-Tableau-Auth token (null until first sign-in).
     */
    protected ?string $token = null;

    /**
     * HTTP request timeout in seconds.
     */
    protected int $timeout = 30;

    public function __construct()
    {
        $this->baseUrl    = config('services.tableau.base_url') ?? '';
        $this->apiVersion = config('services.tableau.api_version') ?? '';
        $this->siteId     = config('services.tableau.site_id') ?? '';
        $this->viewUrl    = config('services.tableau.view_url') ?? '';
        $this->username   = config('credentials.tableau.username') ?? '';
        $this->password   = config('credentials.tableau.password') ?? '';
    }

    /**
     * Sign in to Tableau and cache the X-Tableau-Auth token.
     *
     * @return string The auth token
     * @throws Exception
     */
    public function signIn(): string
    {
        Log::info('TableauApiService: Signing in', [
            'username' => $this->username,
        ]);

        $endpoint = '/api/' . $this->apiVersion . '/auth/signin';

        $body = [
            'credentials' => [
                'name'     => $this->username,
                'password' => $this->password,
                'site'     => ['contentUrl' => ''],
            ],
        ];

        $result = $this->callAPI('POST', $endpoint, $body);

        $token = $result['credentials']['token'] ?? null;

        if (empty($token)) {
            Log::error('TableauApiService: Sign-in succeeded but no token in response', [
                'response_keys' => array_keys($result),
            ]);

            throw new Exception('Tableau sign-in did not return a token');
        }

        $this->token = $token;

        Log::info('TableauApiService: Sign-in successful');

        return $token;
    }

    /**
     * Sign in if no token is cached.
     */
    protected function ensureAuthenticated(): void
    {
        if ($this->token === null) {
            $this->signIn();
        }
    }

    /**
     * Detect a 401 from the wrapped Exception message produced by callAPI().
     */
    protected function looksLikeAuthFailure(Exception $e): bool
    {
        return str_contains($e->getMessage(), 'status 401');
    }

    /**
     * Call Tableau API via Laravel HTTP client.
     *
     * @param string $method HTTP method (POST, GET, PUT)
     * @param string $endpoint Endpoint path (will be appended to baseUrl)
     * @param array $data Request data (JSON body for POST/PUT, query string for GET)
     * @param array $headers Additional headers (e.g. X-Tableau-Auth)
     * @return array Decoded JSON response
     * @throws Exception
     */
    protected function callAPI(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        Log::info('TableauApiService: API Request', [
            'method'     => $method,
            'url'        => $url,
            'data_keys'  => array_keys($data),
            'header_keys' => array_keys($headers),
        ]);

        try {
            $http = Http::timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->withHeaders($headers);

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
            $body     = $response->body();

            Log::info('TableauApiService: API Response', [
                'method'          => $method,
                'url'             => $url,
                'http_code'       => $httpCode,
                'response_length' => strlen($body),
            ]);

            if ($response->failed()) {
                Log::error('TableauApiService: API returned error status', [
                    'method'        => $method,
                    'url'           => $url,
                    'http_code'     => $httpCode,
                    'response_body' => substr($body, 0, 500),
                ]);

                throw new Exception("API request failed with status {$httpCode}: " . substr($body, 0, 200));
            }

            $result = $response->json();

            if ($result === null && !empty($body)) {
                Log::error('TableauApiService: Invalid JSON response', [
                    'response' => substr($body, 0, 500),
                ]);

                throw new Exception('Invalid JSON response from Tableau API');
            }

            return $result ?? [];

        } catch (ConnectionException $e) {
            Log::error('TableauApiService: Connection Failure', [
                'method'        => $method,
                'url'           => $url,
                'error_message' => $e->getMessage(),
            ]);

            throw new Exception("Connection Failure: " . $e->getMessage(), 0, $e);

        } catch (RequestException $e) {
            Log::error('TableauApiService: Request Exception', [
                'method'        => $method,
                'url'           => $url,
                'error_message' => $e->getMessage(),
                'http_code'     => $e->response ? $e->response->status() : null,
            ]);

            throw new Exception("Request failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Set custom base URL (useful for testing).
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * Set API version (e.g. "3.1").
     */
    public function setApiVersion(string $version): self
    {
        $this->apiVersion = $version;
        return $this;
    }

    /**
     * Set the target site ID.
     */
    public function setSiteId(string $siteId): self
    {
        $this->siteId = $siteId;
        return $this;
    }

    /**
     * Set the default view URL.
     */
    public function setViewUrl(string $viewUrl): self
    {
        $this->viewUrl = $viewUrl;
        return $this;
    }

    /**
     * Set credentials (useful for testing).
     */
    public function setCredentials(string $username, string $password): self
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    /**
     * Inject a pre-existing token (skip sign-in).
     */
    public function setToken(?string $token): self
    {
        $this->token = $token;
        return $this;
    }

    /**
     * Set HTTP request timeout.
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
}