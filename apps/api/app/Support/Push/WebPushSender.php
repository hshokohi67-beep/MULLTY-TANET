<?php

namespace App\Support\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Sends one Web Push message. Only well-known push services are ever contacted (the endpoint
 * comes from a browser, so anything else would let a visitor make this server call any URL).
 * Configured with WEBPUSH_PUBLIC_KEY / WEBPUSH_PRIVATE_KEY (base64 PEM) / WEBPUSH_SUBJECT;
 * without keys push is off and every send is a quiet no-op.
 */
final class WebPushSender
{
    /** Push service hosts (exact) and host suffixes ("." prefix). */
    private const SERVICES = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com', '.push.apple.com', '.notify.windows.com'];

    public function __construct(private readonly HttpFactory $http) {}

    public static function enabled(): bool
    {
        return (string) config('webpush.public_key') !== '' && (string) config('webpush.private_key') !== '';
    }

    public static function publicKey(): ?string
    {
        return self::enabled() ? (string) config('webpush.public_key') : null;
    }

    public static function allowedEndpoint(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        foreach (self::SERVICES as $service) {
            if ($service[0] === '.' ? str_ends_with($host, $service) : $host === $service) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload  small JSON (the browser caps it near 4 KB)
     */
    public function send(string $endpoint, string $p256dh, string $auth, array $payload, int $ttl = 3600, string $urgency = 'high'): PushResult
    {
        if (! self::enabled() || ! self::allowedEndpoint($endpoint)) {
            return PushResult::skipped();
        }

        $parts = parse_url($endpoint);
        $audience = 'https://'.($parts['host'] ?? '');
        $privatePem = (string) base64_decode((string) config('webpush.private_key'), true);

        try {
            $body = WebPushCrypto::encrypt((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), WebPushCrypto::unb64($p256dh), WebPushCrypto::unb64($auth));
            $authorization = WebPushCrypto::vapidHeader($audience, (string) config('webpush.subject'), $privatePem, WebPushCrypto::unb64((string) config('webpush.public_key')));
        } catch (\RuntimeException $e) {
            Log::warning('[push] could not prepare message', ['error' => $e->getMessage()]);

            return PushResult::failed(0);
        }

        try {
            $response = $this->http->timeout(10)
                ->withHeaders([
                    'Authorization' => $authorization,
                    'Content-Encoding' => 'aes128gcm',
                    'TTL' => (string) $ttl,
                    'Urgency' => $urgency,
                ])
                ->withBody($body, 'application/octet-stream')
                ->post($endpoint);
        } catch (ConnectionException) {
            Log::warning('[push] push service unreachable', ['host' => $parts['host'] ?? null]);

            return PushResult::failed(0);
        }

        $status = $response->status();
        if ($response->successful()) {
            return PushResult::sent();
        }
        if ($status === 404 || $status === 410) {
            return PushResult::gone($status);
        }
        Log::warning('[push] push service refused', ['host' => $parts['host'] ?? null, 'status' => $status]);

        return PushResult::failed($status);
    }
}
