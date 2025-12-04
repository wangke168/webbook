<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFliggyProductChangeJob implements ShouldQueue
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
        $productId = $this->payload['productId'] ?? 'unknown';

        Log::info("Processing Fliggy Product Change Job", [
            'message_id' => $messageId,
            'push_type' => $pushType,
            'product_id' => $productId
        ]);

        try {
            // 根据 pushType 执行不同逻辑
            switch ($pushType) {
                case 'VACATION_RESOURCE_INFO_CHANGE':
                case 'VACATION_RESOURCE_VALID_CHANGE':
                case 'VACATION_RESOURCE_INVALID_CHANGE':
                    // 触发重新获取产品详情或基本信息
                    // $this->updateProductInfo($productId);
                    Log::info("Triggering product info update for product ID: {$productId}");
                    // TODO: 调用 ProductService 更新产品信息
                    break;

                case 'VACATION_RESOURCE_PRICE_STOCK_CHANGE':
                case 'VACATION_RESOURCE_PRICE_CHANGE':
                case 'VACATION_RESOURCE_STOCK_CHANGE':
                    // 触发重新获取价格库存
                    // $this->updateProductPriceStock($productId);
                    Log::info("Triggering price/stock update for product ID: {$productId}");
                    // TODO: 调用 ProductService 更新价库
                    break;

                default:
                    Log::notice("Unhandled pushType in job", ['push_type' => $pushType, 'payload' => $this->payload]);
                // 可以选择不处理未知类型
            }

            // 可以记录处理历史或发送内部通知
            Log::info("Successfully processed Fliggy Product Change Job", ['message_id' => $messageId]);

        } catch (\Exception $e) {
            Log::error("Error processing Fliggy Product Change Job", [
                'message_id' => $messageId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // 根据 Laravel 队列配置决定是否重试
            throw $e; // 抛出异常让队列处理器决定重试
        }
    }

    // private function updateProductInfo($productId) { ... }
    // private function updateProductPriceStock($productId) { ... }
}
