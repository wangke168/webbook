<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class FliggyApiClient
{
    private Client $client;
    private FliggySignatureService $signatureService;
    private string $appKey;
    private string $appSecret;
    private string $baseUrl;
    private const DEFAULT_API_PATH = '/router/rest';

    public function __construct(FliggySignatureService $signatureService)
    {
        $this->signatureService = $signatureService;

        // Load and validate configurations
        $this->loadAndValidateConfig();

        // Initialize Guzzle client
        $this->client = new Client([
            'timeout' => config('fliggy.timeout', 30),
            // 'verify' => false, // Only for testing with self-signed certs
        ]);
    }

    /**
     * Loads configuration and performs basic validation.
     *
     * @throws InvalidArgumentException
     */
    private function loadAndValidateConfig(): void
    {
        $this->appKey = config('fliggy.app_key');
        $this->appSecret = config('fliggy.app_secret');
        $this->baseUrl = rtrim(config('fliggy.base_url'), '/');

        if (empty($this->appKey)) {
            Log::critical("Fliggy App Key is missing or empty in configuration.");
            throw new InvalidArgumentException("Fliggy App Key not configured.");
        }

        if (empty($this->appSecret)) {
            Log::critical("Fliggy App Secret is missing or empty in configuration.");
            throw new InvalidArgumentException("Fliggy App Secret not configured.");
        }

        if (empty($this->baseUrl) || !filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            Log::critical("Invalid Fliggy Base URL configured.", ['configured_url' => $this->baseUrl ?? 'NULL']);
            throw new InvalidArgumentException("Invalid Fliggy Base URL configured. Must be a full URL including scheme (http/https) and host.");
        }

        Log::debug("FliggyApiClient Configuration Loaded", [
            'app_key' => $this->appKey,
            'base_url' => $this->baseUrl,
            'api_path' => self::DEFAULT_API_PATH
        ]);
    }

    /**
     * Makes a call to the Fliggy API.
     *
     * @param string $method The Fliggy API method name (e.g., alitrip.travel.trades.search).
     * @param array $params Business parameters for the API call.
     * @param string $httpMethod HTTP method ('GET' or 'POST'). Defaults to 'GET'.
     * @return array Decoded JSON response from the API.
     * @throws \Exception On any failure during the process.
     */
    public function call(string $method, array $params = [], string $httpMethod = 'GET'): array
    {
        $httpMethod = strtoupper($httpMethod);
        if (!in_array($httpMethod, ['GET', 'POST'])) {
            throw new InvalidArgumentException("Unsupported HTTP method: $httpMethod. Use GET or POST.");
        }

        // 1. Prepare common system parameters
        $commonParams = [
            'method' => $method,
            'app_key' => $this->appKey,
            'timestamp' => now()->timezone('Asia/Shanghai')->format('Y-m-d H:i:s'), // Standardized format
            'format' => 'json',
            'v' => '2.0',
            'sign_method' => 'hmac', // Important: Use 'hmac' for RSA-SHA256
            'simplify' => 'true',
        ];

        // 2. Merge common and business parameters
        $allParams = array_merge($commonParams, $params);

        // 3. Generate the signature string according to Taobao/Fliggy rules
        $dataToSign = $this->buildDataToSign($allParams);

        // 4. Generate the final signature
        $sign = $this->signatureService->generateSignature($dataToSign);

        // 5. Add the signature to the parameters
        $allParams['sign'] = $sign;

        // 6. Build the full request URL
        $fullRequestUrl = $this->baseUrl . self::DEFAULT_API_PATH;

        Log::info("Calling Fliggy API", [
            'method_name' => $method,
            'http_method' => $httpMethod,
            'request_url' => $fullRequestUrl,
            'final_params_keys' => array_keys($allParams) // Log keys, values might be sensitive
        ]);

        try {
            $options = [];
            $effectiveUrl = $fullRequestUrl; // Default for GET

            if ($httpMethod === 'POST') {
                $options['form_params'] = $allParams;
                Log::debug("Sending POST request with form parameters.", ['param_keys' => array_keys($allParams)]);
            } else { // GET
                // Append parameters to the query string for GET requests
                $queryString = http_build_query($allParams, '', '&', PHP_QUERY_RFC3986); // RFC 3986 encoding is safer
                $effectiveUrl .= '?' . $queryString;
                Log::debug("Constructed GET request URL.", ['query_string_length' => strlen($queryString)]);
            }

            // 7. Make the HTTP request
            /** @var Response $response */
            $response = $this->client->request($httpMethod, $effectiveUrl, $options);

            // 8. Handle the response
            return $this->handleResponse($response, $effectiveUrl, $allParams);

        } catch (GuzzleException $e) {
            Log::error("Guzzle HTTP Exception during Fliggy API call", [
                'message' => $e->getMessage(),
                'request_url' => $fullRequestUrl ?? 'Unknown',
                'http_method' => $httpMethod,
                'params_keys' => array_keys($allParams ?? [])
            ]);
            throw new \Exception("Network error or invalid response from Fliggy API: " . $e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            Log::error("General Exception during Fliggy API call", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw general exceptions
        }
    }

    /**
     * Builds the string that needs to be signed according to Taobao/Fliggy standards.
     * For sign_method=hmac (RSA), it's typically the sorted k-v pairs without wrapping secrets.
     *
     * @param array $params All parameters including system params, before adding 'sign'.
     * @return string The string ready to be signed.
     */
    private function buildDataToSign(array $params): string
    {
        // Remove the 'sign' parameter itself if present
        unset($params['sign']);

        // Sort parameters by key in ascending order (byte order)
        ksort($params);

        $stringToBeSigned = '';
        foreach ($params as $k => $v) {
            // Skip null or empty string values as per common API practice
            if ($v !== null && $v !== '') {
                $stringToBeSigned .= "$k$v"; // Concatenate key and value directly
            }
        }

        // For hmac (RSA-SHA256), the data to sign is usually just the sorted k-v string.
        // Some docs might suggest wrapping with app_secret, but standard practice for RSA is not to.
        // If issues arise, consult official Fliggy docs for the exact rule for sign_method=hmac.
        Log::debug("Built data to sign for Fliggy API", [
            'sorted_param_count' => count($params),
            'string_to_be_signed_snippet' => substr($stringToBeSigned, 0, 200) . (strlen($stringToBeSigned) > 200 ? '...' : '')
        ]);

        return $stringToBeSigned;
    }

    /**
     * Handles the HTTP response from the API.
     *
     * @param Response $response The Guzzle Response object.
     * @param string $requestUrl The URL that was requested.
     * @param array $requestParams The parameters sent with the request.
     * @return array Decoded JSON response.
     * @throws \Exception If the response indicates an error or cannot be decoded.
     */
    private function handleResponse(Response $response, string $requestUrl, array $requestParams): array
    {
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        $headers = $response->getHeaders();
        $contentLength = $response->getHeaderLine('Content-Length');

        // Get the raw body stream and its contents
        $bodyStream = $response->getBody();
        $rawBody = $bodyStream->getContents();

        // Log comprehensive response details for debugging
        Log::debug("Raw Fliggy API Response Received", [
            'status_code' => $statusCode,
            'reason_phrase' => $reasonPhrase,
            'content_length_header' => $contentLength,
            'actual_body_length' => strlen($rawBody),
            'headers' => $headers,
            'raw_body_snippet' => substr($rawBody, 0, 500) . (strlen($rawBody) > 500 ? '...' : '')
        ]);

        // Basic HTTP success check
        if ($statusCode !== 200) {
            Log::warning("Fliggy API returned non-200 HTTP status", [
                'status_code' => $statusCode,
                'reason' => $reasonPhrase,
                'request_url' => $requestUrl
            ]);
            // Sometimes APIs return error info even with non-200, try to parse it
            $errorData = json_decode($rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE && (isset($errorData['error_response']) || isset($errorData['code']))) {
                Log::warning("Fliggy API returned structured error data in non-200 response", ['error_data' => $errorData]);
                throw new \Exception("Fliggy API Error (HTTP $statusCode): " . ($errorData['error_response']['msg'] ?? $errorData['sub_msg'] ?? 'Unknown error'));
            }
            throw new \Exception("Fliggy API returned HTTP error: $statusCode $reasonPhrase. Raw response snippet: " . substr($rawBody, 0, 200));
        }

        // Check for empty body (unexpected for successful API calls)
        if ($rawBody === '' || $rawBody === null) {
            Log::warning("Fliggy API returned HTTP 200 OK but with an empty body.", [
                'request_url' => $requestUrl,
                'request_params_keys' => array_keys($requestParams)
            ]);
            throw new \Exception("Fliggy API returned an empty response body for a successful request.");
        }

        // Attempt to decode JSON
        $decodedResponse = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonErrorMsg = json_last_error_msg();
            Log::error("Failed to decode JSON response from Fliggy API", [
                'json_error_code' => json_last_error(),
                'json_error_message' => $jsonErrorMsg,
                'raw_body_received' => $rawBody,
                'body_length' => strlen($rawBody)
            ]);
            throw new \Exception("Failed to decode JSON response from Fliggy API. JSON Error: $jsonErrorMsg. Body Length: " . strlen($rawBody));
        }

        // Check if the decoded response contains an error structure
        // Common patterns: top-level 'error_response' or fields like 'code', 'msg'
        if (isset($decodedResponse['error_response'])) {
            $errorMsg = $decodedResponse['error_response']['msg'] ?? 'Unknown API error';
            $errorCode = $decodedResponse['error_response']['code'] ?? 'UNKNOWN_CODE';
            Log::warning("Fliggy API returned logical error within JSON", [
                'error_code' => $errorCode,
                'error_message' => $errorMsg,
                'full_response' => $decodedResponse // Log full error response for context
            ]);
            throw new \Exception("Fliggy API Logical Error [$errorCode]: $errorMsg");
        }

        Log::info("Successfully decoded Fliggy API response", [
            'top_level_keys' => array_keys($decodedResponse),
            'response_size_bytes' => strlen($rawBody)
        ]);

        return $decodedResponse;
    }
}
?>
