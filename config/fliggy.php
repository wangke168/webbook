<?php
// config/fliggy.php

return [
    /*
    |--------------------------------------------------------------------------
    | Fliggy API Credentials
    |--------------------------------------------------------------------------
    |
    | Your application credentials obtained from the Fliggy Open Platform.
    |
    */

    'app_key' => env('FLIGGY_DISTRIBUTOR_ID'),
    'app_secret' => env('FLIGGY_PRIVATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Fliggy API Keys (RSA)
    |--------------------------------------------------------------------------
    |
    | Your RSA private key for signing requests and optionally the public key
    | for verifying callbacks.
    | The private key can be the content string or a file path like 'file:///path/to/key.pem'.
    |
    */

    'private_key' => env('FLIGGY_PRIVATE_KEY'),
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
