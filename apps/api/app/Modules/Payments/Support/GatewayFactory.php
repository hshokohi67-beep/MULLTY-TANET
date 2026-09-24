<?php

namespace App\Modules\Payments\Support;

use App\Modules\Commerce\Contracts\OnlinePaymentGate;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Payments\Support\Gateways\FakeGateway;
use App\Modules\Payments\Support\Gateways\PaymentGateway;
use App\Modules\Payments\Support\Gateways\ZarinpalGateway;
use Illuminate\Http\Client\Factory as HttpFactory;
use LogicException;

/**
 * Builds the gateway for the current tenant at call time (credentials are tenant settings,
 * so this must never be resolved once and cached across requests).
 */
final class GatewayFactory implements OnlinePaymentGate
{
    public function __construct(private readonly HttpFactory $http) {}

    /** The configured driver, e.g. for a new online payment. */
    public function driver(): string
    {
        $driver = (string) config('payments.driver');

        if ($driver === 'fake' && ! app()->environment(['local', 'testing'])) {
            throw new LogicException('The fake payment gateway is not allowed in the ['.app()->environment().'] environment.');
        }

        return $driver;
    }

    /** Whether the current tenant can take online payments right now. */
    public function onlineAvailable(): bool
    {
        if (! TenantSettings::get('payments.online.enabled')) {
            return false;
        }

        return $this->driver() === 'fake' || filled(TenantSettings::get('payments.zarinpal.merchant_id'));
    }

    /** The gateway a payment was made with (a verify must use the same one). */
    public function make(string $gateway): PaymentGateway
    {
        return match ($gateway) {
            'fake' => $this->driver() === 'fake' ? new FakeGateway : throw new LogicException('The fake payment gateway is disabled.'),
            'zarinpal' => new ZarinpalGateway(
                $this->http,
                (string) TenantSettings::get('payments.zarinpal.merchant_id') ?: throw PaymentException::onlineUnavailable(),
                (string) (config('payments.gateways.zarinpal.sandbox') ? config('payments.gateways.zarinpal.sandbox_url') : config('payments.gateways.zarinpal.base_url')),
                (int) config('payments.gateways.zarinpal.timeout'),
            ),
            default => throw new LogicException("Unknown payment gateway [{$gateway}]."),
        };
    }
}
