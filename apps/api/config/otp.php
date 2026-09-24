<?php

return [
    // Customer one-time passwords. Codes are CSPRNG-generated, stored hashed, and never returned by the API.
    'length' => (int) env('OTP_LENGTH', 5),
    'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 120),
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'daily_limit_per_phone' => (int) env('OTP_DAILY_LIMIT_PER_PHONE', 8),

    // Sanctum token lifetimes.
    'customer_token_days' => (int) env('CUSTOMER_TOKEN_DAYS', 30),
    'staff_token_hours' => (int) env('STAFF_TOKEN_HOURS', 12),
];
