<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFliggyOrderStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle()
    {
        $messageId = $this->payload['messageId'] ?? 'unknown';
        $pushType = $this->payload['pushType'] ?? 'unknown';
        $fliggyOrderId = $this->payload['orderId'] ?? 'unknown';
        $outOrderId = $this->payload['outOrderId'] ?? 'unknown';
        $data = $this->payload['data'] ?? [];

        Log::info("Processing Fliggy Order Status Job", [
            'message_id' => $messageId,
            'push_type' => $pushType,
            'fliggy_order_id' => $fliggyOrderId,
            'out_order_id' => $outOrderId
        ]);

        try {
            // 查找本地订单记录
            // $order = FliggyOrder::where('order_id', $fliggyOrderId)
            //                      ->orWhere('out_order_id', $outOrderId)
            //                      ->first();

            // if (!$order) {
            //     Log::warning("Order not found locally for webhook", [
            //         'fliggy_order_id' => $fliggyOrderId,
            //         'out_order_id' => $outOrderId
            //     ]);
            //     // 可能需要创建一个临时记录或报警
            //     return;
            // }

            // 根据 pushType 更新订单状态或处理通知
            switch ($pushType) {
                case 'ORDER_STATUS_CHANGE':
                    $newStatus = $data['orderStatus'] ?? null;
                    if ($newStatus) {
                        // $order->update(['status' => $newStatus, 'last_sync_at' => now()]);
                        Log::info("Updating order status", [
                            'fliggy_order_id' => $fliggyOrderId,
                            'new_status' => $newStatus
                        ]);
                        // TODO: 更新订单模型状态
                    }
                    break;

                case 'ORDER_SEND_CODE_NOTIFY':
                    $success = $data['success'] ?? false;
                    if ($success) {
                        $codeInfos = $data['codeInfos'] ?? [];
                        // $order->update(['codes' => $codeInfos, 'status' => 'CODE_SENT']); // 假设有 codes 字段
                        Log::info("Order codes sent successfully", [
                            'fliggy_order_id' => $fliggyOrderId,
                            'code_count' => count($codeInfos)
                        ]);
                        // TODO: 保存凭证码信息
                    } else {
                        $failReason = $data['failReason'] ?? 'Unknown failure';
                        Log::warning("Failed to send order codes", [
                            'fliggy_order_id' => $fliggyOrderId,
                            'reason' => $failReason
                        ]);
                    }
                    break;

                case 'ORDER_REFUND_NOTIFY':
                    $success = $data['success'] ?? false;
                    $refundFee = $data['refundFee'] ?? 0;
                    $refundQuantity = $data['refundQuantity'] ?? 0;
                    if ($success) {
                        Log::info("Order refund successful", [
                            'fliggy_order_id' => $fliggyOrderId,
                            'refund_fee' => $refundFee,
                            'refund_quantity' => $refundQuantity
                        ]);
                        // $order->update([
                        //     'refunded_amount' => $order->refunded_amount + $refundFee,
                        //     'refunded_quantity' => $order->refunded_quantity + $refundQuantity,
                        //     'status' => ($refundQuantity >= $order->quantity) ? 'REFUNDED_FULL' : 'REFUNDED_PARTIAL'
                        // ]);
                        // TODO: 更新退款信息
                    } else {
                        $failReason = $data['failReason'] ?? 'Unknown failure';
                        Log::warning("Order refund failed", [
                            'fliggy_order_id' => $fliggyOrderId,
                            'reason' => $failReason
                        ]);
                    }
                    break;

                case 'ORDER_VERIFY_NOTIFY':
                    $codeInfo = $data['codeInfo'] ?? [];
                    $verifyTime = $data['verifyTime'] ?? '';
                    Log::info("Order code verified", [
                        'fliggy_order_id' => $fliggyOrderId,
                        'code' => $codeInfo['code'] ?? 'unknown',
                        'verify_time' => $verifyTime
                    ]);
                    // TODO: 记录核销信息
                    break;

                default:
                    Log::notice("Unhandled pushType in order job", ['push_type' => $pushType, 'payload' => $this->payload]);
            }

            // 可以触发事件通知其他系统
            // event(new OrderStatusUpdated($order, $pushType, $data));

            Log::info("Successfully processed Fliggy Order Status Job", ['message_id' => $messageId]);

        } catch (\Exception $e) {
            Log::error("Error processing Fliggy Order Status Job", [
                'message_id' => $messageId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
