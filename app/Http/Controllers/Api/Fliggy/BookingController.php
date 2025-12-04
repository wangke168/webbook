<?php

namespace App\Http\Controllers\Api\Fliggy;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingController extends BaseController
{
    /**
     * 订单校验（预下单）
     * POST /api/fliggy/bookings/validate
     * @param Request $request
     * @return JsonResponse
     */
    public function validateOrder(Request $request): JsonResponse
    {
        // 验证参数
        $validated = $request->validate([
            'out_order_id' => 'required|string',
            'product_info' => 'required|array',
            'product_info.product_id' => 'required|integer',
            'product_info.price' => 'required|integer',
            'product_info.quantity' => 'required|integer',
            'product_info.travel_date' => 'required|date_format:Y-m-d',
            'contact_info' => 'required|array',
            'contact_info.name' => 'required|string',
            'contact_info.mobile' => 'required|string',
            'traveller_infos' => 'required|array',
            'total_price' => 'required|integer',
        ]);

        $params = [
            'outOrderId' => $validated['out_order_id'],
            'productInfo' => $validated['product_info'],
            'contactInfo' => $validated['contact_info'],
            'travellerInfos' => $validated['traveller_infos'],
            'totalPrice' => $validated['total_price'],
        ];

        // 添加可选参数
        if ($request->has('secondary_distributor_total_price')) {
            $params['secondaryDistributorTotalPrice'] = $request->input('secondary_distributor_total_price');
        }
        if ($request->has('out_secondary_distributor_code')) {
            $params['outSecondaryDistributorCode'] = $request->input('out_secondary_distributor_code');
        }

        return $this->callFliggyApi('validateOrder', $params, 'POST');
    }

    /**
     * 订单创建
     * POST /api/fliggy/bookings/create
     * @param Request $request
     * @return JsonResponse
     */
    public function createOrder(Request $request): JsonResponse
    {
        // 验证参数
        $validated = $request->validate([
            'out_order_id' => 'required|string',
            'product_info' => 'required|array',
            'product_info.product_id' => 'required|integer',
            'product_info.price' => 'required|integer',
            'product_info.quantity' => 'required|integer',
            'product_info.travel_date' => 'required|date_format:Y-m-d',
            'contact_info' => 'required|array',
            'contact_info.name' => 'required|string',
            'contact_info.mobile' => 'required|string',
            'traveller_infos' => 'required|array',
            'total_price' => 'required|integer',
        ]);

        $params = [
            'outOrderId' => $validated['out_order_id'],
            'productInfo' => $validated['product_info'],
            'contactInfo' => $validated['contact_info'],
            'travellerInfos' => $validated['traveller_infos'],
            'totalPrice' => $validated['total_price'],
        ];

        // 添加可选参数
        if ($request->has('secondary_distributor_total_price')) {
            $params['secondaryDistributorTotalPrice'] = $request->input('secondary_distributor_total_price');
        }
        if ($request->has('out_secondary_distributor_code')) {
            $params['outSecondaryDistributorCode'] = $request->input('out_secondary_distributor_code');
        }

        return $this->callFliggyApi('createOrder', $params, 'POST');
    }
}
