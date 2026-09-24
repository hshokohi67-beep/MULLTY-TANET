<?php

namespace App\Modules\Identity\Support;

use App\Modules\Core\Models\Tenant;
use App\Modules\Identity\Exceptions\OtpException;
use App\Support\Sms\SmsProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tenant-scoped one-time passwords for customer login.
 *
 * Fixes the legacy weaknesses: CSPRNG code, stored as an HMAC (never plaintext),
 * limited verify attempts, resend cooldown, daily cap, and the code is never
 * returned in an API response regardless of environment or request headers.
 */
final class OtpService
{
    public function __construct(
        private readonly Cache $cache,
        private readonly SmsProvider $sms,
    ) {}

    /**
     * @return array{expires_in: int, resend_after: int}
     */
    public function issue(Tenant $tenant, string $phoneE164): array
    {
        $cooldownKey = $this->key('cooldown', $tenant, $phoneE164);
        $dailyKey = $this->key('daily', $tenant, $phoneE164).':'.now()->toDateString();

        $cooldownUntil = $this->cache->get($cooldownKey);

        if (is_int($cooldownUntil) && $cooldownUntil > time()) {
            throw OtpException::cooldown($cooldownUntil - time());
        }

        $sentToday = (int) $this->cache->get($dailyKey, 0);

        if ($sentToday >= (int) config('otp.daily_limit_per_phone')) {
            throw OtpException::dailyLimit();
        }

        $code = $this->generateCode();
        $ttl = (int) config('otp.ttl_seconds');
        $cooldown = (int) config('otp.resend_cooldown_seconds');

        $this->cache->put($this->key('code', $tenant, $phoneE164), [
            'hash' => $this->hash($tenant, $phoneE164, $code),
            'attempts' => 0,
            'expires_at' => time() + $ttl,
        ], $ttl);

        $result = $this->sms->sendVerificationCode($phoneE164, $code);

        if (! $result->successful) {
            $this->cache->forget($this->key('code', $tenant, $phoneE164));
            Log::warning('[otp] delivery failed', ['tenant' => $tenant->getKey(), 'error' => $result->error]);

            throw OtpException::deliveryFailed();
        }

        $this->cache->put($cooldownKey, time() + $cooldown, $cooldown);
        $this->cache->put($dailyKey, $sentToday + 1, now()->endOfDay());

        return ['expires_in' => $ttl, 'resend_after' => $cooldown];
    }

    /**
     * Consumes the code on success. Throws on any failure.
     */
    public function verify(Tenant $tenant, string $phoneE164, string $code): void
    {
        $key = $this->key('code', $tenant, $phoneE164);
        $entry = $this->cache->get($key);

        if (! is_array($entry) || $entry['expires_at'] <= time()) {
            $this->cache->forget($key);

            throw OtpException::expired();
        }

        if ($entry['attempts'] >= (int) config('otp.max_attempts')) {
            $this->cache->forget($key);

            throw OtpException::tooManyAttempts();
        }

        if (! hash_equals($entry['hash'], $this->hash($tenant, $phoneE164, $code))) {
            $entry['attempts']++;
            $remaining = $entry['expires_at'] - time();

            if ($entry['attempts'] >= (int) config('otp.max_attempts') || $remaining <= 0) {
                $this->cache->forget($key);

                throw OtpException::tooManyAttempts();
            }

            $this->cache->put($key, $entry, $remaining);

            throw OtpException::invalid();
        }

        $this->cache->forget($key);
    }

    private function generateCode(): string
    {
        $length = max(4, (int) config('otp.length'));

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(Tenant $tenant, string $phoneE164, string $code): string
    {
        return hash_hmac('sha256', $tenant->getKey().'|'.$phoneE164.'|'.$code, (string) config('app.key'));
    }

    private function key(string $type, Tenant $tenant, string $phoneE164): string
    {
        return sprintf('otp:%s:%s:%s', $type, $tenant->getKey(), hash('sha256', $phoneE164));
    }
}
