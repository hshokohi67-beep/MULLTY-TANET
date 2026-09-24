<?php

namespace App\Modules\Payments\Support;

use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentTransaction;
use App\Modules\Payments\Support\Gateways\GatewayResult;

/**
 * Writes the append-only gateway log. Payloads are already free of credentials
 * (adapters never return them); keys that look secret are redacted as a second line of defence.
 */
final class PaymentLog
{
    private const SECRET_KEYS = ['merchant_id', 'merchant', 'api_key', 'token', 'access_token', 'password', 'card_hash'];

    public function write(Payment $payment, string $action, GatewayResult $result): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'payment_id' => $payment->id,
            'action' => $action,
            'success' => $result->success,
            'gateway_code' => $result->transient ? ($result->code ?? 'transient') : $result->code,
            'http_status' => $result->httpStatus,
            'request' => $this->redact($result->request),
            'response' => $this->redact($result->response),
            'duration_ms' => $result->durationMs,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SECRET_KEYS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                /** @var array<string, mixed> $value */
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
