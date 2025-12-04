<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Fliggy\ProductController;
use App\Http\Controllers\Api\Fliggy\OrderController;
use App\Http\Controllers\Api\Fliggy\BookingController;
use App\Http\Controllers\Api\Fliggy\WebhookController;

Route::get('/', function () {
    return view('welcome');
});




Route::get('product', [ProductController::class, 'getProductList']);

// 其他 API 路由 (如之前定义的 products, orders)
Route::prefix('fliggy')->group(function () {

    // --- 产品相关 ---
    Route::prefix('products')->controller(ProductController::class)->group(function () {
        Route::get('/debug', 'debug')->name('fliggy.products.debug'); // 调试接口
        Route::get('/list', 'getProductList')->name('fliggy.products.list'); // 分页获取产品列表
        Route::post('/batch', 'getProductsByIds')->name('fliggy.products.batch'); // 根据ID批量获取产品
        Route::post('/sync-all', 'syncAllProducts')->name('fliggy.products.sync_all'); // 批量同步所有产品
        Route::get('/{productId}', 'getProductDetail')->name('fliggy.products.detail'); // 获取产品详情
        Route::get('/{productId}/price-stock', 'getPriceStock')->name('fliggy.products.price_stock'); // 获取价格库存
    });

    // --- 订单相关 ---
    Route::prefix('orders')->controller(OrderController::class)->group(function () {
        Route::get('/list', 'getOrderList')->name('fliggy.orders.list'); // 获取订单列表
        Route::get('/{orderId}', 'getOrderDetail')->name('fliggy.orders.detail'); // 获取订单详情
        Route::get('/{orderId}/codes', 'getOrderCodes')->name('fliggy.orders.codes'); // 获取订单凭证码
        // 可继续添加其他订单路由...
    });

    // --- 预订相关 (下单、取消、退款等) ---
    Route::prefix('bookings')->controller(BookingController::class)->group(function () {
        Route::post('/create', 'createOrder')->name('fliggy.bookings.create'); // 创建订单
        Route::post('/{orderId}/cancel', 'cancelOrder')->name('fliggy.bookings.cancel'); // 取消订单
        Route::post('/{orderId}/refund', 'applyRefund')->name('fliggy.bookings.refund'); // 申请退款
        Route::get('/refunds/{refundId}', 'getRefundDetail')->name('fliggy.bookings.refund_detail'); // 查询退款详情
        // 可继续添加其他预订路由...
    });

    // --- Webhook ---
    Route::prefix('webhooks/fliggy')->controller(WebhookController::class)->group(function () {
        Route::post('/product-change', 'handleProductChange')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]) // 确保排除 CSRF
            ->name('fliggy.webhook.product.change');
        Route::post('/order-status', 'handleOrderStatus')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
            ->name('fliggy.webhook.order.status');
    });
});
