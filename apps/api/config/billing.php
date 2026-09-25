<?php

return [
    // Subscription fees go to the platform's own merchant, never a café's.
    'gateway' => env('BILLING_GATEWAY', env('PAYMENTS_DRIVER', 'fake')), // fake (local/testing only) | zarinpal
    'zarinpal' => [
        'merchant_id' => env('BILLING_ZARINPAL_MERCHANT'),
    ],

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    // Full access after an unpaid end, then read-only.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),
    // Value-added tax on subscription invoices, percent.
    'vat_rate' => (int) env('BILLING_VAT_RATE', 10),
];
