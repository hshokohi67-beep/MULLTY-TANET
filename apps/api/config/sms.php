<?php

return [
    /*
     | Platform-level SMS provider used for OTP and system messages.
     | Platform-level SMS provider: login codes (customers and staff) and messages from the platform
     | to café owners. Supported: "log" (local/testing only), "array" (testing), "raygan", "kavenegar".
     | Everything a café sends to its own customers goes through that café's own panel (Messaging).
     */
    'default' => env('SMS_PROVIDER', 'log'),

    'providers' => [
        // RayganSMS / Trez: login codes go through its OTP service line (SendMessageWithCode).
        'raygan' => [
            'username' => env('RAYGAN_USERNAME'),
            'password' => env('RAYGAN_PASSWORD'),
            'sender' => env('RAYGAN_SENDER'),
            'code_template' => env('RAYGAN_CODE_TEMPLATE', "کد ورود شما: :code\n:app"),
        ],
        'kavenegar' => [
            'api_key' => env('KAVENEGAR_API_KEY'),
            'sender' => env('KAVENEGAR_SENDER'),
            'verify_template' => env('KAVENEGAR_VERIFY_TEMPLATE', 'verify'),
            'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com/v1'),
            'timeout' => (int) env('KAVENEGAR_TIMEOUT', 10),
        ],
    ],
];
