<?php

namespace Tests\Feature\Payments;

use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Models\TenantSetting;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Models\DiscountUsage;
use App\Modules\Payments\Models\PaymentTransaction;
use App\Modules\Payments\Support\GatewayFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use LogicException;

final class OnlinePaymentTest extends PaymentsTestCase
{
    public function test_online_checkout_is_refused_unless_enabled(): void
    {
        $cart = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart, $this->variant($this->espresso));

        $this->checkout($cart, ['payment_method' => 'online'])->assertUnprocessable()->assertJsonPath('code', 'online_payment_unavailable');

        // Zarinpal without a merchant ID is not "available" either.
        config(['payments.driver' => 'zarinpal']);
        $this->enableOnline(withMerchant: false);
        $this->checkout($cart, ['payment_method' => 'online'])->assertUnprocessable()->assertJsonPath('code', 'online_payment_unavailable');
    }

    public function test_online_orders_below_the_gateway_minimum_are_refused(): void
    {
        $this->enableOnline();
        config(['payments.min_online_amount' => 1_000_000]);
        $cart = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart, $this->variant($this->espresso));

        $this->checkout($cart, ['payment_method' => 'online'])->assertUnprocessable()->assertJsonPath('code', 'online_payment_below_minimum');
    }

    public function test_an_order_waiting_for_payment_is_not_sent_to_the_kitchen(): void
    {
        Event::fake([OrderPlaced::class]);
        $this->enableOnline();

        $order = $this->onlineOrder();

        Event::assertNotDispatched(OrderPlaced::class);
        $this->assertSame([], $this->getJson('/api/v1/orders?status=open', $this->staffHeaders($this->owner, $this->tenant))->assertOk()->json('data'));
        $this->getJson("/api/v1/public/orders/{$order['id']}", $this->publicHeaders(['X-Order-Token' => $order['token']]))
            ->assertJsonPath('data.status_label', 'در انتظار پرداخت')
            ->assertJsonPath('data.remaining_due', 650_000)
            ->assertJsonPath('data.history.0.to', 'pending_payment');
    }

    public function test_zarinpal_flow_from_request_to_verified_order(): void
    {
        Event::fake([OrderPlaced::class]);
        $this->enableOnline();
        $this->useZarinpal();
        $order = $this->onlineOrder();

        $start = $this->pay($order)->assertOk()
            ->assertJsonPath('data.amount', 650_000)
            ->assertJsonPath('data.redirect_url', self::SANDBOX.'/pg/StartPay/A0000000000000000000000000000abcde1');
        $paymentId = $start->json('data.payment_id');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/request.json')
            && $r['merchant_id'] === self::MERCHANT
            && $r['amount'] === 650_000
            && $r['currency'] === 'IRR'
            && $r['callback_url'] === "https://shop.test/s/cafe-a/pay/{$paymentId}");
        $this->getJson("/api/v1/public/orders/{$order['id']}", $this->publicHeaders(['X-Order-Token' => $order['token']]))->assertJsonPath('data.payment_status', 'pending');

        $this->verify($paymentId, 'A0000000000000000000000000000abcde1')->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.ref_id', '201')
            ->assertJsonPath('data.card_pan', '502229******5995')
            ->assertJsonPath('data.order.status', 'placed')
            ->assertJsonPath('data.order.payment_status', 'paid')
            ->assertJsonPath('data.order.remaining_due', 0)
            ->assertJsonPath('data.order.tracking_token', $order['token']);

        // The verify used the stored amount and the stored authority.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/verify.json') && $r['amount'] === 650_000 && $r['authority'] === 'A0000000000000000000000000000abcde1');
        Event::assertDispatchedTimes(OrderPlaced::class, 1);

        // Refreshing the result page is harmless: no second verify call, no second event.
        $this->verify($paymentId, 'A0000000000000000000000000000abcde1')->assertOk()->assertJsonPath('data.status', 'paid');
        Http::assertSentCount(2);
        Event::assertDispatchedTimes(OrderPlaced::class, 1);

        // Every gateway call is logged, without the merchant ID.
        $log = $this->inTenant($this->tenant, fn () => PaymentTransaction::query()->where('payment_id', $paymentId)->orderBy('created_at')->get());
        $this->assertSame(['request', 'verify'], $log->pluck('action')->all());
        $this->assertStringNotContainsString(self::MERCHANT, json_encode($log->toArray(), JSON_THROW_ON_ERROR));

        // Staff see the paid order on the board.
        $this->getJson('/api/v1/orders?status=open', $this->staffHeaders($this->owner, $this->tenant))
            ->assertJsonPath('data.0.id', $order['id'])
            ->assertJsonPath('data.0.paid_total', 650_000);
    }

    public function test_starting_twice_reuses_the_open_attempt(): void
    {
        $this->enableOnline();
        $this->useZarinpal();
        $order = $this->onlineOrder();

        $first = $this->pay($order)->assertOk()->json('data');
        $second = $this->pay($order)->assertOk()->json('data');

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_verification_needs_the_matching_authority_and_the_order_needs_its_token(): void
    {
        $this->enableOnline();
        $order = $this->onlineOrder();
        $paymentId = $this->pay($order)->assertOk()->json('data.payment_id');

        $this->verify($paymentId, 'FAKEWRONG')->assertNotFound()->assertJsonPath('code', 'payment_not_found');
        $this->verify('01jzzzzzzzzzzzzzzzzzzzzzzz', $this->authorityOf($paymentId))->assertNotFound();
        $this->postJson("/api/v1/public/orders/{$order['id']}/pay", [], $this->publicHeaders(['X-Order-Token' => 'wrong']))->assertNotFound();
        $this->assertSame('pending', $this->payment($paymentId)->status->value);
    }

    public function test_a_declined_payment_keeps_the_order_waiting_and_can_be_retried(): void
    {
        $this->enableOnline();
        $this->useZarinpal(verify: ['data' => [], 'errors' => ['code' => -51, 'message' => 'Session is not valid, session is not active paid try.']]);
        $order = $this->onlineOrder();
        $paymentId = $this->pay($order)->json('data.payment_id');

        $this->verify($paymentId, 'A0000000000000000000000000000abcde1')->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_message', 'پرداخت انجام نشد یا توسط مشتری لغو شد.')
            ->assertJsonPath('data.order.status', 'pending_payment')
            ->assertJsonPath('data.order.payment_status', 'unpaid');

        // A new attempt is opened (the failed one is not reused).
        $this->assertNotSame($paymentId, $this->pay($order)->assertOk()->json('data.payment_id'));
    }

    public function test_a_gateway_timeout_during_verify_leaves_the_payment_pending(): void
    {
        $this->enableOnline();
        $this->useZarinpal();
        $order = $this->onlineOrder();
        $paymentId = $this->pay($order)->json('data.payment_id');

        Http::fake([self::SANDBOX.'/pg/v4/payment/verify.json' => fn () => throw new ConnectionException('timed out')]);

        $this->verify($paymentId, 'A0000000000000000000000000000abcde1')->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.order.status', 'pending_payment');
        $this->assertSame('network', $this->inTenant($this->tenant, fn () => PaymentTransaction::query()->where('action', 'verify')->value('gateway_code')));
    }

    public function test_a_rejected_request_fails_the_attempt(): void
    {
        $this->enableOnline();
        $this->useZarinpal(request: ['data' => [], 'errors' => ['code' => -10, 'message' => 'Terminal is not valid']]);
        $order = $this->onlineOrder();

        $this->pay($order)->assertStatus(502)->assertJsonPath('code', 'payment_gateway_error');
        $this->assertSame('pending_payment', $this->inTenant($this->tenant, fn () => Order::query()->findOrFail($order['id'])->status->value));
        $this->getJson("/api/v1/orders/{$order['id']}/payments", $this->staffHeaders($this->owner, $this->tenant))
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.failure_message', 'مرچنت کد درگاه نامعتبر یا غیرفعال است.');
    }

    public function test_reconcile_confirms_a_customer_who_paid_but_never_came_back(): void
    {
        Event::fake([OrderPlaced::class]);
        $this->enableOnline();
        $order = $this->onlineOrder();
        $this->pay($order)->assertOk();

        $this->travel(16)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $fresh = $this->inTenant($this->tenant, fn () => Order::query()->findOrFail($order['id']));
        $this->assertSame('placed', $fresh->status->value);
        $this->assertSame('paid', $fresh->payment_status->value);
        Event::assertDispatchedTimes(OrderPlaced::class, 1);
    }

    public function test_reconcile_expires_unpaid_attempts_and_cancels_the_order_giving_the_discount_back(): void
    {
        $this->enableOnline();
        $this->useZarinpal(verify: ['data' => [], 'errors' => ['code' => -51, 'message' => 'not paid']]);
        $discount = $this->inTenant($this->tenant, fn () => Discount::query()->create(['name' => 'همیشگی', 'kind' => 'percent', 'value' => 1000, 'applies_to' => 'order']));
        $order = $this->onlineOrder();
        $paymentId = $this->pay($order)->json('data.payment_id');
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => $discount->fresh()?->used_count));

        $this->travel(16)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('expired', $this->payment($paymentId)->status->value);
        $this->assertSame('pending_payment', $this->inTenant($this->tenant, fn () => Order::query()->findOrFail($order['id'])->status->value));

        $this->travel(15)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->inTenant($this->tenant, function () use ($order, $discount): void {
            $fresh = Order::query()->with('history')->findOrFail($order['id']);
            $this->assertSame('cancelled', $fresh->status->value);
            $this->assertSame('پرداخت در مهلت مقرر انجام نشد', $fresh->cancel_reason);
            $this->assertSame(0, $discount->fresh()?->used_count);
            $this->assertFalse(DiscountUsage::query()->where('order_id', $order['id'])->exists());
        });
    }

    public function test_a_payment_arriving_after_cancellation_is_kept_and_flagged_for_refund(): void
    {
        $this->enableOnline();
        $order = $this->onlineOrder();
        $paymentId = $this->pay($order)->json('data.payment_id');

        $this->postJson("/api/v1/orders/{$order['id']}/status", ['status' => 'cancelled', 'note' => 'مشتری رفت'], $this->staffHeaders($this->owner, $this->tenant))->assertOk();

        $this->verify($paymentId, $this->authorityOf($paymentId))->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.order.status', 'cancelled');
        $this->getJson("/api/v1/orders/{$order['id']}", $this->staffHeaders($this->owner, $this->tenant))
            ->assertJsonPath('data.needs_refund', true)
            ->assertJsonPath('data.paid_total', 650_000);
    }

    public function test_merchant_id_is_encrypted_masked_and_validated(): void
    {
        $headers = $this->staffHeaders($this->owner, $this->tenant);

        $this->patchJson('/api/v1/tenant/settings', ['settings' => ['payments.zarinpal.merchant_id' => 'not-a-merchant']], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('settings.payments.zarinpal.merchant_id');

        $items = collect($this->patchJson('/api/v1/tenant/settings', ['settings' => ['payments.zarinpal.merchant_id' => self::MERCHANT, 'payments.online.enabled' => true]], $headers)
            ->assertOk()->json('data'))->keyBy('key');

        $this->assertNull($items['payments.zarinpal.merchant_id']['value']);
        $this->assertSame('••••••••05be', $items['payments.zarinpal.merchant_id']['masked']);
        $this->assertTrue($items['payments.online.enabled']['value']);
        $raw = $this->inTenant($this->tenant, fn () => TenantSetting::query()->where('key', 'payments.zarinpal.merchant_id')->value('value'));
        $this->assertStringNotContainsString(self::MERCHANT, (string) $raw);
    }

    public function test_the_fake_gateway_is_refused_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(LogicException::class);
        app(GatewayFactory::class)->driver();
    }
}
