<?php

namespace App\Http\Controllers\Api\Fliggy;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingController extends BaseController
{
    /**
     * 创建订单 (下单)
     * POST /api/fliggy/bookings/create
     * @param Request $request
     * @return JsonResponse
     */
    public function createOrder(Request $request): JsonResponse
    {
        // 注意：alitrip.travel.trade.create 是复杂对象传参，需要构建符合要求的 JSON/XML 结构
        // 这里简化处理，假设前端或服务层已经构造好了 $mainOrderData 字符串
        // 实际应用中需要仔细处理嵌套对象序列化

        $mainOrderData = $request->input('main_order_data'); // 应该是一个 JSON 字符串

        if (empty($mainOrderData)) {
            return response()->json([
                'success' => false,
                'error_code' => 'MISSING_PARAMETERS',
                'message' => 'main_order_data is required.'
            ], 400);
        }

        // 验证 JSON 格式 (可选但推荐)
        $decodedData = json_decode($mainOrderData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_JSON',
                'message' => 'main_order_data is not valid JSON.',
                'details' => json_last_error_msg()
            ], 400);
        }

        $params = [
            'main_order' => $mainOrderData, // Fliggy API 要求传递的是序列化后的字符串
        ];

        return $this->callFliggyApi('alitrip.travel.trade.create', $params, 'POST'); // 通常下单用 POST
    }

    /**
     * 取消订单
     * POST /api/fliggy/bookings/{orderId}/cancel
     * @param string $orderId
     * @param Request $request
     * @return JsonResponse
     */
    public function cancelOrder(string $orderId, Request $request): JsonResponse
    {
        $params = [
            'order_id' => $orderId,
            'close_reason' => $request->input('close_reason', 'Buyer canceled'), // 关闭原因
            'operator' => $request->input('operator', 'SYSTEM'), // 操作人
        ];

        return $this->callFliggyApi('alitrip.travel.trade.close', $params, 'POST');
    }

    /**
     * 申请退款
     * POST /api/fliggy/bookings/{orderId}/refund
     * @param string $orderId
     * @param Request $request
     * @return JsonResponse
     */
    public function applyRefund(string $orderId, Request $request): JsonResponse
    {
        // alitrip.travel.refund.new.apply 也是复杂对象传参
        $refundApplyData = $request->input('refund_apply_data'); // 应该是一个 JSON 字符串

        if (empty($refundApplyData)) {
            return response()->json([
                'success' => false,
                'error_code' => 'MISSING_PARAMETERS',
                'message' => 'refund_apply_data is required.'
            ], 400);
        }

        $decodedData = json_decode($refundApplyData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_JSON',
                'message' => 'refund_apply_data is not valid JSON.',
                'details' => json_last_error_msg()
            ], 400);
        }

        $params = [
            'refund_apply_req' => $refundApplyData,
        ];

        return $this->callFliggyApi('alitrip.travel.refund.new.apply', $params, 'POST');
    }

    /**
     * 查询退款详情
     * GET /api/fliggy/bookings/refunds/{refundId}
     * @param string $refundId
     * @return JsonResponse
     */
    public function getRefundDetail(string $refundId): JsonResponse
    {
        $params = [
            'refund_id' => $refundId,
        ];

        return $this->callFliggyApi('alitrip.travel.refund.new.get', $params);
    }

    // 可以添加更多预订相关的 API 调用，例如：
    // - 重发凭证码 (alitrip.travel.trade.code.resend)
    // - 核销通知确认 (如果需要主动确认) (可能没有直接 API，通过 webhook)
}
