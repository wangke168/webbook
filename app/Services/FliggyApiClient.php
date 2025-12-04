<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class FliggyApiClient
{
    private $client;
    private $signatureService;
    private $appKey;
    private $appSecret; // Note: For RSA, appSecret might not be directly used in signing, but often as boundary in string construction
    private $baseUrl;

    public function __construct(FliggySignatureService $signatureService)
    {
        $this->signatureService = $signatureService;
        $this->appKey = config('fliggy.app_key');
        $this->appSecret = config('fliggy.app_secret'); // Might be used as boundary
        $this->baseUrl = rtrim(config('api_test_base_url'), '/');

        $this->client = new Client([
            'timeout' => config('fliggy.timeout', 30),
            // 'verify' => false, // For testing with self-signed certs only
        ]);
    }

    /**
     * @throws \Exception
     */
    public function call(string $method, array $params = [], string $httpMethod = 'GET'): array
    {
        // 1. Prepare common parameters
        // IMPORTANT: Change sign_method to 'rsa' for RSA-SHA256 signing
        $commonParams = [
            'method' => $method,
            'app_key' => $this->appKey,
            'timestamp' => now()->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
            'format' => 'json',
            'v' => '2.0',
            'sign_method' => 'rsa', // <-- CHANGED FROM 'md5'
            'simplify' => 'true',
        ];

        // 2. Merge common and business parameters
        $allParams = array_merge($commonParams, $params);

        // 3. Generate the string to sign according to Taobao/Fliggy rules
        // --- Start of String-to-Sign Generation Logic ---
        unset($allParams['sign']); // Ensure 'sign' is not included

        ksort($allParams); // Sort by key ascending

        $stringToBeSigned = '';
        foreach ($allParams as $k => $v) {
            if(!is_null($v) && $v !== '') { // Skip null or empty values
                // According to Taobao API docs, concatenate key and value directly
                // Some variations might require urlencoding keys/values, but usually not for signing string
                $stringToBeSigned .= "$k$v";
            }
        }

        // For many Taobao/Fliggy signatures (especially MD5), the app_secret is prepended/appended to the string.
        // Although your sign_method is 'rsa', some implementations still use app_secret as boundaries.
        // It's safer to include it unless documentation says otherwise.
        // Check Fliggy docs specifically for RSA signing string format.
        // Common pattern: $dataToSign = $appSecret . $stringToBeSigned . $appSecret;
        // However, since your generateSignature only takes $dataToSign, we pass the concatenated string.
        // Let's assume the standard way is to just use the concatenated string for RSA.
        // If that fails, try wrapping with app_secret.
        $dataToSign = $this->appSecret . $stringToBeSigned . $this->appSecret;

        // Alternative if the above doesn't work (uncomment one of the lines below to test):
        // $dataToSign = $this->appSecret . $stringToBeSigned . $this->appSecret; // Try with app_secret boundaries

        // --- End of String-to-Sign Generation Logic ---

        Log::debug("Fliggy API Signing Process", [
            'string_to_be_signed' => $stringToBeSigned,
            'final_data_to_sign' => $dataToSign // Log what will be passed to generateSignature
        ]);

        // 4. Generate the final signature using the RSA service
        // The service method expects only the string to sign
        $sign = $this->signatureService->generateSignature($dataToSign);

        // 5. Add the generated (base64 encoded) signature back to the parameters
        $allParams['sign'] = $sign;

        // 6. Build the request URL
        $requestUrl = $this->baseUrl . 'https://pre-api.alitrip.alibaba.com/router/rest';

        Log::debug("Fliggy API Request", [
            'url' => $requestUrl,
            'method' => $httpMethod,
            'final_params' => $allParams
        ]);

        try {
            $options = [];

            if (strtoupper($httpMethod) === 'POST') {
                $options['form_params'] = $allParams;
            } else { // GET
                $queryString = http_build_query($allParams);
                $requestUrl .= '?' . $queryString;
            }

            // 7. Make the HTTP request
            $response = $this->client->request($httpMethod, $requestUrl, $options);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            Log::debug("Fliggy API Response", [
                'status_code' => $statusCode,
                'body' => $body
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: Status Code {$statusCode}, Body: {$body}");
            }

            $decodedResponse = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Failed to decode JSON response: " . json_last_error_msg() . ". Raw response: " . $body);
            }

            return $decodedResponse;

        } catch (GuzzleException $e) {
            Log::error("Guzzle HTTP Exception in FliggyApiClient", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_url' => $requestUrl,
                'params' => $allParams
            ]);
            throw new \Exception("Network error or invalid response from Fliggy API: " . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            Log::error("General Exception in FliggyApiClient", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
