<?php

return [
    /*
     | Gateway driver for online payments. Merchant credentials are per tenant (tenant settings);
     | this only chooses the implementation. Supported: "zarinpal", "fake" (local/testing only).
     */
    'driver' => env('PAYMENTS_DRIVER', 'fake'),

    // Where the customer returns after the gateway: {storefront_url}/s/{tenant}/pay/{payment}.
    'storefront_url' => rtrim((string) env('STOREFRONT_URL', 'http://localhost:3765'), '/'),

    // Zarinpal rejects amounts below 1,000 toman.
    'min_online_amount' => 10_000,

    // An open attempt is reused for this long, then the reconcile job verifies/expires it.
    'attempt_reuse_minutes' => 10,
    'attempt_ttl_minutes' => 15,

    // Orders still waiting for online payment after this long are cancelled.
    'order_payment_window_minutes' => 30,

    'gateways' => [
        'zarinpal' => [
            'sandbox' => (bool) env('ZARINPAL_SANDBOX', true),
            'base_url' => env('ZARINPAL_BASE_URL', 'https://payment.zarinpal.com'),
            'sandbox_url' => env('ZARINPAL_SANDBOX_URL', 'https://sandbox.zarinpal.com'),
            'timeout' => (int) env('ZARINPAL_TIMEOUT', 10),
        ],
    ],
];
