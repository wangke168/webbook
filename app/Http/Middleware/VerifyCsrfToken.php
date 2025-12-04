<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // 排除 Fliggy Webhook 路径
        'api/webhooks/fliggy/*',
        // 或者更具体
        // 'api/webhooks/fliggy/product-change',
        // 'api/webhooks/fliggy/order-status',
    ];
}
