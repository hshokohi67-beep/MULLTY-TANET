<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Payments\Support\Gateways\FakeGateway;
use App\Modules\Payments\Support\Gateways\PaymentGateway;
use App\Modules\Payments\Support\Gateways\ZarinpalGateway;
use Illuminate\Http\Client\Factory as HttpFactory;
use LogicException;

/**
 * The platform's own gateway (subscription fees go to the platform, never to a café's merchant).
 * Configured in `config/billing.php`; the fake driver is refused outside local/testing.
 */
final class BillingGateway
{
    public function __construct(private readonly HttpFactory $http) {}

    public function driver(): string
    {
        $driver = (string) config('billing.gateway');
        if ($driver === 'fake' && ! app()->environment(['local', 'testing'])) {
            throw new LogicException('The fake billing gateway is not allowed in the ['.app()->environment().'] environment.');
        }

        return $driver;
    }

    public function make(?string $name = null): PaymentGateway
    {
        return match ($name ?? $this->driver()) {
            'fake' => new FakeGateway,
            'zarinpal' => new ZarinpalGateway(
                $this->http,
                (string) config('billing.zarinpal.merchant_id') ?: throw BillingException::gatewayFailed(),
                (string) (config('payments.gateways.zarinpal.sandbox') ? config('payments.gateways.zarinpal.sandbox_url') : config('payments.gateways.zarinpal.base_url')),
                (int) config('payments.gateways.zarinpal.timeout'),
            ),
            default => throw new LogicException('Unknown billing gateway.'),
        };
    }
}
