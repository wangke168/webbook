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
            'page_no' => $request->input('page_no', 1),
            'page_size' => min($request->input('page_size', 20), 100), // 限制最大页大小
            'modified_start' => $request->input('modified_start'), // 增量更新开始时间 (ISO 8601)
            'modified_end' => $request->input('modified_end'),     // 增量更新结束时间 (ISO 8601)
            // 可根据需要添加其他过滤参数
        ];
        // 过滤掉空值
        $params = array_filter($params, fn($value) => $value !== null && $value !== '');

        return $this->callFliggyApi('alitrip.travel.item.list', $params);
    }

    /**
     * 获取产品详细信息 (单个)
     * GET /api/fliggy/products/{itemId}
     * @param string $itemId
     * @return JsonResponse
     */
    public function getProductDetail(string $itemId): JsonResponse
    {
        $params = [
            'item_id' => $itemId,
        ];

        return $this->callFliggyApi('alitrip.travel.item.get', $params);
    }

    /**
     * 获取产品价格日历 (指定日期范围内的价格和库存)
     * GET /api/fliggy/products/{itemId}/price-calendar
     * @param string $itemId
     * @param Request $request
     * @return JsonResponse
     */
    public function getPriceCalendar(string $itemId, Request $request): JsonResponse
    {
        $params = [
            'item_id' => $itemId,
            'start_date' => $request->input('start_date'), // 必填, YYYY-MM-DD
            'end_date' => $request->input('end_date'),     // 必填, YYYY-MM-DD
        ];

        // 验证必填参数
        if (empty($params['start_date']) || empty($params['end_date'])) {
            return response()->json([
                'success' => false,
                'error_code' => 'MISSING_PARAMETERS',
                'message' => 'start_date and end_date are required.'
            ], 400);
        }

        return $this->callFliggyApi('alitrip.travel.item.price.calendar', $params);
    }

    // 可以添加更多产品相关的 API 调用，例如：
    // - 获取产品套餐信息 (alitrip.travel.item.package.get)
    // - 获取产品可售日期 (alitrip.travel.item.date.get)
    // - 获取产品 SKU 信息 (alitrip.travel.item.sku.get)
}
