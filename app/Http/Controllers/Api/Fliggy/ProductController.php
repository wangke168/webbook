<?php

namespace App\Http\Controllers\Api\Fliggy;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends BaseController
{
    /**
     * 获取产品列表 (全量或增量)
     * GET /api/fliggy/products/list
     * @param Request $request
     * @return JsonResponse
     */
    public function getProductList(Request $request): JsonResponse
    {
        $params = [
            // 根据文档 P7，使用驼峰命名
            'pageNo' => $request->input('page_no', 1),
            'pageSize' => min($request->input('page_size', 20), 100),
            'modifiedStart' => $request->input('modified_start'),
            'modifiedEnd' => $request->input('modified_end'),
            // 可根据需要添加其他过滤参数
        ];
        // 过滤掉空值
        $params = array_filter($params, fn($value) => $value !== null && $value !== '');

        return $this->callFliggyApi('queryProductBaseInfoByPage', $params);
    }

    /**
     * 获取单个产品详细信息
     * GET /api/fliggy/products/detail/{productId}
     * 注意：这里假设路由参数是 productId，而非 itemId
     * @param string $productId
     * @return JsonResponse
     */
    public function getProductDetail(string $productId): JsonResponse
    {
        $params = [
            'productId' => $productId, // 根据文档 P8
        ];

        return $this->callFliggyApi('queryProductDetailInfo', $params);
    }

    /**
     * 获取产品价格和库存信息 (注意：文档中此接口参数可能需要调整)
     * GET /api/fliggy/products/prices-stocks?product_ids=ID1,ID2&start_time=...&end_time=...
     * 或者 POST /api/fliggy/products/prices-stocks
     * @param Request $request
     * @return JsonResponse
     */
    public function getPriceCalendar(Request $request): JsonResponse
    {
        // 根据文档 P9, queryProductPriceStock 需要 productIds (复数, 逗号分隔), startTime, endTime
        // 这里假设前端传入的是 product_ids 字符串 "ID1,ID2" 或数组
        $productIdsInput = $request->input('product_ids');

        if (is_string($productIdsInput)) {
            $productIdsString = $productIdsInput; // Already comma-separated?
        } elseif (is_array($productIdsInput)) {
            $productIdsString = implode(',', $productIdsInput);
        } else {
            // 如果是获取单个产品价格，可能需要调整逻辑或使用不同的参数名
            // 或者要求必须传入 product_ids
            $singleProductId = $request->input('product_id'); // Fallback or alternative param name
            if($singleProductId) {
                $productIdsString = $singleProductId;
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'MISSING_PARAMETERS',
                    'message' => 'product_ids (comma-separated string or array) or product_id is required.'
                ], 400);
            }
        }

        $params = [
            'productIds' => $productIdsString, // 根据文档 P9
            'startTime' => $request->input('start_time'), // 根据文档 P9
            'endTime' => $request->input('end_time'),     // 根据文档 P9
        ];

        // 验证必填参数
        if (empty($params['startTime']) || empty($params['endTime'])) {
            return response()->json([
                'success' => false,
                'error_code' => 'MISSING_PARAMETERS',
                'message' => 'start_time and end_time are required.'
            ], 400);
        }

        // 文档显示这个接口可能更适合 POST，但也可以试试 GET
        return $this->callFliggyApi('queryProductPriceStock', $params /* , 'POST' */ );
    }

}
?>
