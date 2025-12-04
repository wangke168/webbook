<?php

return [
    // Fliggy 分配的分销商 ID
    'distributor_id' => env('FLIGGY_DISTRIBUTOR_ID', null),

    // Fliggy 分配的私钥 (用于签名)
    'private_key' => env('FLIGGY_PRIVATE_KEY', null),

    // Fliggy API 接口地址 (生产环境)
    'api_base_url' => env('FLIGGY_API_BASE_URL', 'https://api.alitrip.alibaba.com'),

    // Fliggy API 测试环境地址
    'api_test_base_url' => env('FLIGGY_API_TEST_BASE_URL', 'https://pre-api.alitrip.alibaba.com'),

    // 是否使用测试环境
    'use_sandbox' => env('FLIGGY_USE_SANDBOX', false),

    // 默认响应格式 (json/xml)
    'default_format' => env('FLIGGY_DEFAULT_FORMAT', 'json'),

    // Fliggy 公钥 (用于验证 Fliggy 推送过来的消息签名 - 如有提供)
    'public_key' => env('FLIGGY_PUBLIC_KEY', null),

    // Webhook URLs (用于调试或记录，非必需)
    'webhook_urls' => [
        'product_change' => env('FLIGGY_WEBHOOK_PRODUCT_CHANGE', null),
        'order_status' => env('FLIGGY_WEBHOOK_ORDER_STATUS', null),
    ],
];
