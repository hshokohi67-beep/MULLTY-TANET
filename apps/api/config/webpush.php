<?php

// Browser push (Web Push + VAPID). Generate a key pair with `php artisan push:vapid`.
// Without keys push stays off and nothing is sent.
return [
    'public_key' => env('WEBPUSH_PUBLIC_KEY', ''),   // base64url, uncompressed P-256 point
    'private_key' => env('WEBPUSH_PRIVATE_KEY', ''), // base64 of the PEM private key
    'subject' => env('WEBPUSH_SUBJECT', 'mailto:support@cafeyar.ir'),
];
