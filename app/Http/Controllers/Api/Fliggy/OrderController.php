<?php

namespace App\Http\Controllers\Api\Fliggy;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends BaseController
{
    /**
     * 订单查询接口
     * GET /api/fliggy/orders/search
     * @param Request $request
     * @return JsonResponse
     */
    public function searchOrder(Request $request): JsonResponse
    {
        // 验证参数
        $validated = $request->validate([
            'order_id' => 'required_without:out_order_id|integer',
            'out_order_id' => 'required_without:order_id|string',
        ]);

        $params = [];
        if (isset($validated['order_id'])) {
            $params['orderId'] = $validated['order_id'];
        }
        if (isset($validated['out_order_id'])) {
            $params['outOrderId'] = $validated['out_order_id'];
        }

        return $this->callFliggyApi('searchOrder', $params, 'POST');
    }

    /**
     * 订单取消接口
     * POST /api/fliggy/orders/cancel
     * @param Request $request
     * @return JsonResponse
     */
    public function cancelOrder(Request $request): JsonResponse
    {
        // 验证参数
        $validated = $request->validate([
            'order_id' => 'required_without:out_order_id|integer',
            'out_order_id' => 'required_without:order_id|string',
            'reason' => 'nullable|string',
        ]);

        $params = [];
        if (isset($validated['order_id'])) {
            $params['orderId'] = $validated['order_id'];
        }
        if (isset($validated['out_order_id'])) {
            $params['outOrderId'] = $validated['out_order_id'];
        }
        if (isset($validated['reason'])) {
            $params['reason'] = $validated['reason'];
        }

        return $this->callFliggyApi('cancelOrder', $params, 'POST');
    }

    /**
     * 订单退款接口
     * POST /api/fliggy/orders/refund
     * @param Request $request
     * @return JsonResponse
     */
    public function refundOrder(Request $request): JsonResponse
    {
        // 验证参数
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'refund_reason' => 'nullable|string',
            'remark' => 'nullable|string',
        ]);

        $params = [
            'orderId' => $validated['order_id'],
        ];

        if (isset($validated['refund_reason'])) {
            $params['refundReason'] = $validated['refund_reason'];
        }
        if (isset($validated['remark'])) {
            $params['remark'] = $validated['remark'];
        }

        return $this->callFliggyApi('refundOrder', $params, 'POST');
    }

    /**
     * 查询退款单
     * GET /api/fliggy/orders/refund/search
     * @param Request $request
     * @return JsonResponse
     */
    public function searchRefundOrder(Request $request): JsonResponse
    {
        // 验证参数
        $validated = $request->validate([
            'order_id' => 'required_without:out_order_id|integer',
            'out_order_id' => 'required_without:order_id|string',
            'distributor_refund_id' => 'nullable|string',
        ]);

        $params = [];
        if (isset($validated['order_id'])) {
            $params['orderId'] = $validated['order_id'];
        }
        if (isset($validated['out_order_id'])) {
            $params['outOrderId'] = $validated['out_order_id'];
        }
        if (isset($validated['distributor_refund_id'])) {
            $params['distributorRefundId'] = $validated['distributor_refund_id'];
        }

        return $this->callFliggyApi('searchRefundOrder', $params, 'POST');
    }
}
