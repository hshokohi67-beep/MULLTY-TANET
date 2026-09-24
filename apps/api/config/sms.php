<?php

return [
    /*
     | Platform-level SMS provider used for OTP and system messages.
     | Supported: "log" (local/testing only), "array" (testing), "kavenegar".
     | Tenant-owned SMS panels (campaigns) arrive with the Notifications module.
     */
    'default' => env('SMS_PROVIDER', 'log'),

    'providers' => [
        'kavenegar' => [
            'api_key' => env('KAVENEGAR_API_KEY'),
            'sender' => env('KAVENEGAR_SENDER'),
            'verify_template' => env('KAVENEGAR_VERIFY_TEMPLATE', 'verify'),
            'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com/v1'),
            'timeout' => (int) env('KAVENEGAR_TIMEOUT', 10),
        ],
    ],
];
