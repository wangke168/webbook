<?php

namespace App\Http\Controllers\Api\Fliggy;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends BaseController
{
    /**
     * 分页获取产品基本信息列表
     * GET /api/fliggy/products/list
     * @param Request $request
     * @return JsonResponse
     */
    public function getProductList(Request $request): JsonResponse
    {
        // 验证参数
        $validated = $request->validate([
            'page_no' => 'integer|min:1',
            'page_size' => 'integer|min:1|max:100',
        ]);

        $params = [
            'pageNo' => $validated['page_no'] ?? 1,
            'pageSize' => $validated['page_size'] ?? 20,
        ];

        return $this->callFliggyApi('queryProductBaseInfoByPage', $params, 'POST');
    }

    /**
     * 根据产品ID列表批量获取产品基本信息
     * POST /api/fliggy/products/batch
     * @param Request $request
     * @return JsonResponse
     */
    public function getProductsByIds(Request $request): JsonResponse
    {
        // 验证参数
        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:100',
            'product_ids.*' => 'required|string',
        ]);

        $params = [
            'productIds' => $validated['product_ids'],
        ];

        return $this->callFliggyApi('queryProductBaseInfoByIds', $params, 'POST');
    }

    /**
     * 获取产品详细信息 (单个)
     * GET /api/fliggy/products/{productId}
     * @param string $productId
     * @return JsonResponse
     */
    public function getProductDetail(string $productId): JsonResponse
    {
        $params = [
            'productId' => $productId,
        ];

        return $this->callFliggyApi('queryProductDetailInfo', $params, 'POST');
    }

    /**
     * 获取产品价格库存信息
     * GET /api/fliggy/products/{productId}/price-stock
     * @param string $productId
     * @param Request $request
     * @return JsonResponse
     */
    public function getPriceStock(string $productId, Request $request): JsonResponse
    {
        // 验证参数
        $validated = $request->validate([
            'begin_time' => 'integer', // 13位时间戳
            'end_time' => 'integer',   // 13位时间戳
        ]);

        $params = [
            'productId' => $productId,
        ];

        // 添加可选的时间范围参数
        if (isset($validated['begin_time'])) {
            $params['beginTime'] = $validated['begin_time'];
        }
        if (isset($validated['end_time'])) {
            $params['endTime'] = $validated['end_time'];
        }

        return $this->callFliggyApi('queryProductPriceStock', $params, 'POST');
    }

    /**
     * 批量获取所有产品基本信息（分页遍历）
     * POST /api/fliggy/products/sync-all
     * @param Request $request
     * @return JsonResponse
     */
    public function syncAllProducts(Request $request): JsonResponse
    {
        $pageSize = min($request->input('page_size', 100), 100);
        $allProducts = [];
        $pageNo = 1;
        $totalPages = 1;

        try {
            do {
                $params = [
                    'pageNo' => $pageNo,
                    'pageSize' => $pageSize,
                ];

                $response = $this->apiClient->call('queryProductBaseInfoByPage', $params, 'POST');

                // 检查响应是否成功
                if (isset($response['success']) && $response['success'] === false) {
                    return response()->json([
                        'success' => false,
                        'error_code' => $response['code'] ?? 'UNKNOWN_ERROR',
                        'message' => $response['msg'] ?? 'Failed to fetch products',
                    ], 400);
                }

                // 提取产品列表
                if (isset($response['data']['productBaseInfos'])) {
                    $products = $response['data']['productBaseInfos'];
                    $allProducts = array_merge($allProducts, $products);
                    
                    // 如果返回的产品数量少于页大小，说明已经是最后一页
                    if (count($products) < $pageSize) {
                        break;
                    }
                }

                $pageNo++;

                // 添加延迟避免请求过快
                usleep(200000); // 0.2秒

            } while ($pageNo <= 1000); // 设置最大页数限制，防止无限循环

            return response()->json([
                'success' => true,
                'data' => [
                    'total_products' => count($allProducts),
                    'total_pages' => $pageNo - 1,
                    'products' => $allProducts,
                ],
                'message' => 'Successfully synced all products'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to sync all products", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error_code' => 'SYNC_ERROR',
                'message' => 'Failed to sync all products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 调试接口 - 查看原始响应
     * GET /api/fliggy/products/debug
     */
    public function debug(Request $request): JsonResponse
    {
        try {
            $params = [
                'pageNo' => 1,
                'pageSize' => 10,
            ];

            // 直接调用 API 不要包装
            $response = $this->apiClient->call('queryProductBaseInfoByPage', $params, 'POST');

            return response()->json([
                'success' => true,
                'raw_response' => $response,
                'response_keys' => array_keys($response),
                'message' => 'Debug info'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
