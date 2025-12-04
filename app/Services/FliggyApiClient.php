<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FliggyApiClient
{
    protected $baseUrl;
    protected $distributorId;
    protected $format;
    protected $signatureService;

    public function __construct(FliggySignatureService $signatureService)
    {
        $this->signatureService = $signatureService;
        $this->distributorId = config('fliggy.distributor_id');
        $this->format = config('fliggy.default_format', 'json');

        if (config('fliggy.use_sandbox', false)) {
            $this->baseUrl = config('fliggy.api_test_base_url');
        } else {
            $this->baseUrl = config('fliggy.api_base_url');
        }

        if (!$this->baseUrl || !$this->distributorId) {
            throw new \Exception("Fliggy API base URL or Distributor ID not configured.");
        }
    }

    /**
     * 发起 API 调用
     *
     * @param string $method API 方法名
     * @param array $params API 参数
     * @param string $httpMethod HTTP 方法 (GET/POST)
     * @return array|\Illuminate\Http\Client\Response
     * @throws \Exception
     */
    public function call(string $method, array $params = [], string $httpMethod = 'GET')
    {
        $timestamp = time() . '000'; // 毫秒级时间戳

        // 合并公共参数
        $fullParams = array_merge($params, [
            'method' => $method,
            'timestamp' => $timestamp,
            'format' => $this->format,
            'v' => '2.0', // API 版本
            'sign_method' => 'sha256',
            'partner_id' => $this->distributorId,
        ]);

        // 构造待签名字符串 (param)
        ksort($fullParams);
        $paramString = implode('', array_values($fullParams));
        Log::debug("Fliggy API Call - Param String for Signing", ['method' => $method, 'param' => $paramString]);

        // 生成签名
        $signature = $this->signatureService->generateSignature($paramString);
        $fullParams['sign'] = $signature;

        $url = rtrim($this->baseUrl, '/') . '/router/rest';

        try {
            Log::info("Calling Fliggy API", ['url' => $url, 'method' => $method, 'params' => $params]);

            $response = Http::timeout(30)->asForm(); // 根据 Fliggy 要求可能需要 form-data 或 www-form-urlencoded

            if (strtoupper($httpMethod) === 'POST') {
                $response = $response->post($url, $fullParams);
            } else {
                $response = $response->get($url, $fullParams);
            }

            $responseBody = $response->body();
            $decodedResponse = json_decode($responseBody, true);

            Log::info("Fliggy API Response", [
                'method' => $method,
                'status_code' => $response->status(),
                'raw_response' => $responseBody,
                'parsed_response' => $decodedResponse
            ]);

            if ($response->successful()) {
                return $decodedResponse ?: $responseBody; // 返回解析后的数组或原始字符串
            } else {
                Log::error("Fliggy API Call Failed (HTTP Error)", [
                    'method' => $method,
                    'status_code' => $response->status(),
                    'response_body' => $responseBody
                ]);
                throw new \Exception("Fliggy API HTTP Error: " . $response->status());
            }

        } catch (\Exception $e) {
            Log::error("Fliggy API Call Exception", [
                'method' => $method,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
