<?php

namespace App\Http\Controllers\Api\Fliggy;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFliggyProductChangeJob;
use App\Jobs\ProcessFliggyOrderStatusJob;
use App\Services\FliggySignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected $signatureService;

    public function __construct(FliggySignatureService $signatureService)
    {
        // 禁用 CSRF 验证对于 webhook 路由
        // 在 app/Http/Middleware/VerifyCsrfToken.php 的 $except 数组中添加路径
        $this->signatureService = $signatureService;
    }

    /**
     * 处理产品变更推送
     * POST /api/webhooks/fliggy/product-change
     */
    public function handleProductChange(Request $request)
    {
        $startTime = microtime(true);
        $payload = $request->all();
        Log::info("Fliggy Product Change Webhook Received", ['payload' => $payload]);

        // 1. 验证签名 (强烈建议)
        $isValidSignature = $this->verifyWebhookSignature($request, $payload);
        if (!$isValidSignature) {
            Log::warning("Fliggy Product Change Webhook Signature Invalid");
            return response('error', 400); // Fliggy 要求失败响应 'error'
        }

        // 2. 基本参数校验
        $requiredFields = ['pushType', 'productCategory', 'productId', 'distributorId', 'changedTime', 'timestamp', 'messageId'];
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                Log::warning("Missing required field in Product Change Webhook", ['missing_field' => $field, 'payload' => $payload]);
                return response('error', 400);
            }
        }

        // 3. 验证 distributorId
        if ((string)$payload['distributorId'] !== (string)config('fliggy.distributor_id')) {
            Log::warning("Mismatched Distributor ID in Product Change Webhook", ['received' => $payload['distributorId'], 'configured' => config('fliggy.distributor_id')]);
            return response('error', 400);
        }

        // 4. 根据 pushType 处理 (这里简化处理，实际可以更细致)
        $allowedPushTypes = [
            'VACATION_RESOURCE_INFO_CHANGE',
            'VACATION_RESOURCE_VALID_CHANGE',
            'VACATION_RESOURCE_INVALID_CHANGE',
            'VACATION_RESOURCE_PRICE_STOCK_CHANGE',
            'VACATION_RESOURCE_PRICE_CHANGE',
            'VACATION_RESOURCE_STOCK_CHANGE'
        ];

        if (!in_array($payload['pushType'], $allowedPushTypes)) {
            Log::notice("Unknown or unsupported pushType in Product Change Webhook", ['pushType' => $payload['pushType']]);
            // 根据策略决定是否返回 error 或 success
            // return response('error', 400);
        }

        // 5. 放入队列异步处理，避免阻塞 webhook 响应
        ProcessFliggyProductChangeJob::dispatch($payload);

        $duration = round((microtime(true) - $startTime) * 1000, 2); // 计算耗时 (ms)
        Log::info("Fliggy Product Change Webhook Accepted for Processing", ['message_id' => $payload['messageId'], 'duration_ms' => $duration]);

        // 6. 返回成功响应给 Fliggy
        return response('success', 200); // Fliggy 要求成功响应 'success'
    }

    /**
     * 处理订单状态推送
     * POST /api/webhooks/fliggy/order-status
     */
    public function handleOrderStatus(Request $request)
    {
        $startTime = microtime(true);
        $payload = $request->all();
        Log::info("Fliggy Order Status Webhook Received", ['payload' => $payload]);

        // 1. 验证签名 (强烈建议)
        $isValidSignature = $this->verifyWebhookSignature($request, $payload);
        if (!$isValidSignature) {
            Log::warning("Fliggy Order Status Webhook Signature Invalid");
            return response('error', 400);
        }

        // 2. 基本参数校验
        $requiredFields = ['pushType', 'distributorId', 'orderId', 'outOrderId', 'timestamp', 'messageId', 'data'];
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                Log::warning("Missing required field in Order Status Webhook", ['missing_field' => $field, 'payload' => $payload]);
                return response('error', 400);
            }
        }

        // 3. 验证 distributorId
        if ((string)$payload['distributorId'] !== (string)config('fliggy.distributor_id')) {
            Log::warning("Mismatched Distributor ID in Order Status Webhook", ['received' => $payload['distributorId'], 'configured' => config('fliggy.distributor_id')]);
            return response('error', 400);
        }

        // 4. 根据 pushType 处理
        $allowedPushTypes = [
            'ORDER_STATUS_CHANGE',
            'ORDER_SEND_CODE_NOTIFY',
            'ORDER_REFUND_NOTIFY',
            'ORDER_VERIFY_NOTIFY'
        ];

        if (!in_array($payload['pushType'], $allowedPushTypes)) {
            Log::notice("Unknown or unsupported pushType in Order Status Webhook", ['pushType' => $payload['pushType']]);
            // return response('error', 400);
        }

        // 5. 放入队列异步处理
        ProcessFliggyOrderStatusJob::dispatch($payload);

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        Log::info("Fliggy Order Status Webhook Accepted for Processing", ['message_id' => $payload['messageId'], 'duration_ms' => $duration]);

        // 6. 返回成功响应给 Fliggy
        return response('success', 200);
    }

    /**
     * 验证 Webhook 请求的签名
     * @param Request $request
     * @param array $payload
     * @return bool
     */
    protected function verifyWebhookSignature(Request $request, array $payload): bool
    {
        // 从请求头或请求体获取签名 (根据 Fliggy 实际发送方式调整)
        // 假设签名在请求体的 'sign' 字段
        $receivedSignature = $payload['sign'] ?? '';
        if (empty($receivedSignature)) {
            Log::warning("No signature found in webhook payload.");
            return false;
        }

        // 重建签名字符串 param
        // 注意：需要排除 'sign' 字段本身
        unset($payload['sign']); // 重要：移除 sign 字段
        ksort($payload); // 按 key 字典序排序
        $paramString = implode(',', array_values($payload)); // 注意：这里是逗号分隔

        Log::debug("Reconstructed param for signature verification", ['param' => $paramString]);

        return $this->signatureService->verifySignature($paramString, $receivedSignature);
    }
}
