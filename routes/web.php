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
        Route::get('/search', 'searchOrder')->name('fliggy.orders.search'); // 订单查询
        Route::post('/cancel', 'cancelOrder')->name('fliggy.orders.cancel'); // 订单取消
        Route::post('/refund', 'refundOrder')->name('fliggy.orders.refund'); // 订单退款
        Route::get('/refund/search', 'searchRefundOrder')->name('fliggy.orders.refund.search'); // 查询退款单
    });

    // --- 预订相关 (下单、取消、退款等) ---
    Route::prefix('bookings')->controller(BookingController::class)->group(function () {
        Route::post('/validate', 'validateOrder')->name('fliggy.bookings.validate'); // 订单校验（预下单）
        Route::post('/create', 'createOrder')->name('fliggy.bookings.create'); // 创建订单
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
