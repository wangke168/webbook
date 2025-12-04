<?php
// config/fliggy.php

return [
    /*
    |--------------------------------------------------------------------------
    | Fliggy API Credentials
    |--------------------------------------------------------------------------
    |
    | Your application credentials obtained from the Fliggy Open Platform.
    | app_secret: 飞猪提供的privateKey，用于RSA-SHA256签名
    |
    */

    'distributor_id' => env('FLIGGY_DISTRIBUTOR_ID'),
    'app_secret' => env('FLIGGY_APP_SECRET'), // 飞猪提供的privateKey
    'app_key' => env('FLIGGY_APP_KEY', null), // 仅淘宝开放平台接口需要

    /*
    |--------------------------------------------------------------------------
    | Fliggy API Keys (RSA)
    |--------------------------------------------------------------------------
    |
    | RSA私钥用于签名请求，公钥用于验证回调。
    | 注意：飞猪自有API使用app_secret作为privateKey
    | private_key仅用于淘宝开放平台API（可选）
    | The private key can be the content string or a file path like 'file:///path/to/key.pem'.
    |
    */

    'private_key' => env('FLIGGY_PRIVATE_KEY'), // 淘宝开放平台专用（可选）
    'public_key' => env('FLIGGY_PUBLIC_KEY', null), // Optional

    /*
    |--------------------------------------------------------------------------
    | Fliggy API Settings
    |--------------------------------------------------------------------------
    */

    'base_url' => env('FLIGGY_BASE_URL', 'https://eco.taobao.com'), // Production
    // 'base_url' => env('FLIGGY_BASE_URL', 'https://pre-api.alitrip.alibaba.com'), // Sandbox/Pre-release

    'timeout' => env('FLIGGY_TIMEOUT', 30),

];
?>
