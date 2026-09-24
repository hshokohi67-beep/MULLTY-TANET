<?php

namespace Tests\Feature\Payments;

use App\Modules\Core\Models\TenantSetting;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Commerce\CommerceTestCase;

abstract class PaymentsTestCase extends CommerceTestCase
{
    protected const MERCHANT = '1344b5d4-0048-11e8-94db-005056a205be';

    protected const SANDBOX = 'https://sandbox.zarinpal.com';

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.driver' => 'fake', 'payments.storefront_url' => 'https://shop.test', 'payments.gateways.zarinpal.sandbox' => true]);
    }

    protected function enableOnline(bool $withMerchant = true): void
    {
        $this->inTenant($this->tenant, function () use ($withMerchant): void {
            TenantSetting::query()->updateOrCreate(['key' => 'payments.online.enabled'], ['value' => '1', 'is_encrypted' => false]);

            if ($withMerchant) {
                $setting = TenantSetting::query()->firstOrNew(['key' => 'payments.zarinpal.merchant_id']);
                $setting->storeValue(self::MERCHANT, true);
                $setting->save();
            }
        });
    }

    /** Switches to the real Zarinpal adapter with faked HTTP responses. */
    protected function useZarinpal(array $request = ['data' => ['code' => 100, 'message' => 'Success', 'authority' => 'A0000000000000000000000000000abcde1', 'fee' => 0], 'errors' => []], ?array $verify = null): void
    {
        config(['payments.driver' => 'zarinpal']);
        $calls = 0;

        Http::fake([
            // Like the real gateway, every request gets a new authority (…abcde1, …abcde2, …).
            self::SANDBOX.'/pg/v4/payment/request.json' => function () use ($request, &$calls) {
                $calls++;
                if (isset($request['data']['authority'])) {
                    $request['data']['authority'] = substr($request['data']['authority'], 0, -1).$calls;
                }

                return Http::response($request, isset($request['errors']['code']) ? 400 : 200);
            },
            self::SANDBOX.'/pg/v4/payment/verify.json' => Http::response($verify ?? ['data' => ['code' => 100, 'ref_id' => 201, 'card_pan' => '502229******5995', 'fee' => 1000], 'errors' => []]),
        ]);
    }

    /**
     * A guest QR order for one espresso (650,000 rial) with online payment.
     *
     * @return array{id: string, token: string}
     */
    protected function onlineOrder(): array
    {
        $cart = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart, $this->variant($this->espresso))->assertCreated();
        $response = $this->checkout($cart, ['payment_method' => 'online'])->assertCreated()->assertJsonPath('data.status', 'pending_payment');

        return ['id' => $response->json('data.id'), 'token' => $response->json('tracking_token')];
    }

    /** @param  array{id: string, token: string}  $order */
    protected function pay(array $order): TestResponse
    {
        return $this->postJson("/api/v1/public/orders/{$order['id']}/pay", [], $this->publicHeaders(['X-Order-Token' => $order['token']]));
    }

    protected function verify(string $paymentId, string $authority): TestResponse
    {
        return $this->postJson("/api/v1/public/payments/{$paymentId}/verify", ['authority' => $authority], $this->publicHeaders());
    }

    protected function payment(string $id): Payment
    {
        return $this->inTenant($this->tenant, fn () => Payment::query()->findOrFail($id));
    }

    protected function authorityOf(string $paymentId): string
    {
        return (string) $this->payment($paymentId)->authority;
    }
}
