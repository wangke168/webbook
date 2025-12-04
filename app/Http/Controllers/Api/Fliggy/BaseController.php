<?php

namespace App\Http\Controllers\Api\Fliggy;

use App\Http\Controllers\Controller;
use App\Services\FliggyApiClient;
use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{
    protected $apiClient;

    public function __construct(FliggyApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * 统一处理 API 调用结果
     *
     * @param string $method API 方法名
     * @param array $params API 参数
     * @param string $httpMethod HTTP 方法
     * @return JsonResponse
     */
    protected function callFliggyApi(string $method, array $params = [], string $httpMethod = 'GET'): JsonResponse
    {
        try {
            $response = $this->apiClient->call($method, $params, $httpMethod);

            // 检查飞猪API业务层面的错误（success=false）
            if (isset($response['success']) && $response['success'] === false) {
                $errorCode = $response['code'] ?? 'UNKNOWN_ERROR';
                $errorMsg = $response['msg'] ?? 'Unknown error occurred.';
                
                \Illuminate\Support\Facades\Log::warning("Fliggy API Business Error", [
                    'method' => $method,
                    'error_code' => $errorCode,
                    'error_message' => $errorMsg,
                    'full_response' => $response
                ]);
                
                return response()->json([
                    'success' => false,
                    'error_code' => $errorCode,
                    'message' => $errorMsg,
                    'details' => $response
                ], 200); // 返回200状态码，但success=false
            }

            // 检查 Fliggy API 返回的错误码 (假设错误信息在特定字段)
            if (isset($response['error_response'])) {
                $errorCode = $response['error_response']['code'] ?? 'UNKNOWN_ERROR';
                $errorMsg = $response['error_response']['msg'] ?? 'Unknown error occurred.';
                \Illuminate\Support\Facades\Log::warning("Fliggy API Error Response", [
                    'method' => $method,
                    'error_code' => $errorCode,
                    'error_message' => $errorMsg,
                    'response' => $response
                ]);
                return response()->json([
                    'success' => false,
                    'error_code' => $errorCode,
                    'message' => $errorMsg,
                    'details' => $response['error_response']
                ], 200);
            }

            // 成功响应
            return response()->json([
                'success' => true,
                'data' => $response,
                'message' => 'API call successful'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Fliggy API Call Exception in Controller", [
                'method' => $method,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Internal server error while calling Fliggy API',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
