<?php

namespace App\Http\Controllers\Api\Fliggy;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends BaseController
{
    /**
     * 查询订单列表
     * GET /api/fliggy/orders/list
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderList(Request $request): JsonResponse
    {
        $params = [
            'page_no' => $request->input('page_no', 1),
            'page_size' => min($request->input('page_size', 20), 100),
            'start_time' => $request->input('start_time'), // 下单开始时间 (ISO 8601)
            'end_time' => $request->input('end_time'),     // 下单结束时间 (ISO 8601)
            'order_status' => $request->input('order_status'), // 订单状态过滤
            'out_trade_no' => $request->input('out_trade_no'), // 外部交易号过滤
            // 可根据需要添加其他查询条件
        ];
        $params = array_filter($params, fn($value) => $value !== null && $value !== '');

        return $this->callFliggyApi('alitrip.travel.trade.query', $params);
    }

    /**
     * 查询单个订单详情
     * GET /api/fliggy/orders/{orderId}
     * @param string $orderId
     * @return JsonResponse
     */
    public function getOrderDetail(string $orderId): JsonResponse
    {
        $params = [
            'order_id' => $orderId,
        ];

        return $this->callFliggyApi('alitrip.travel.trade.get', $params);
    }

    /**
     * 查询订单凭证码
     * GET /api/fliggy/orders/{orderId}/codes
     * @param string $orderId
     * @return JsonResponse
     */
    public function getOrderCodes(string $orderId): JsonResponse
    {
        $params = [
            'order_id' => $orderId,
        ];

        return $this->callFliggyApi('alitrip.travel.trade.code.get', $params);
    }


    // 可以添加更多订单相关的 API 调用，例如：
    // - 订单日志查询 (alitrip.travel.trade.log.get)
    // - 订单评价查询 (alitrip.travel.trade.rate.get)
}
