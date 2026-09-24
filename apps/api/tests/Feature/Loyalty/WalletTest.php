<?php

namespace Tests\Feature\Loyalty;

use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Loyalty\Actions\PostWalletTransaction;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Modules\Loyalty\Exceptions\LoyaltyException;
use App\Modules\Loyalty\Models\WalletTransaction;
use Illuminate\Support\Facades\Event;

final class WalletTest extends ClubTestCase
{
    public function test_random_operation_sequences_keep_the_ledger_exact_and_never_overdraw(): void
    {
        [$customer] = $this->customer();
        mt_srand(20260924);
        $expected = 0;

        $this->inTenant($this->tenant, function () use ($customer, &$expected): void {
            $post = app(PostWalletTransaction::class);

            for ($i = 0; $i < 120; $i++) {
                $amount = mt_rand(-400, 600) * 1000;
                if ($amount === 0) {
                    continue;
                }

                try {
                    $post->handle($customer, WalletTransactionType::Adjustment, $amount, mt_rand(0, 4) === 0 ? 'k'.mt_rand(0, 20) : null);
                    $expected = (int) WalletTransaction::query()->sum('amount');
                } catch (LoyaltyException) {
                    $this->assertLessThan(0, $expected + $amount, 'only overdrafts are refused');
                }

                $this->assertGreaterThanOrEqual(0, $expected);
            }
        });

        $this->assertSame($expected, $this->walletBalance($customer));
        $this->assertLedgersConsistent($customer);
    }

    public function test_a_repeated_idempotency_key_posts_once(): void
    {
        [$customer] = $this->customer();

        $this->inTenant($this->tenant, function () use ($customer): void {
            $post = app(PostWalletTransaction::class);
            $first = $post->handle($customer, WalletTransactionType::Birthday, 50_000, 'birthday:x:1405');
            $second = $post->handle($customer, WalletTransactionType::Birthday, 50_000, 'birthday:x:1405');
            $this->assertSame($first->id, $second->id);
        });

        $this->assertSame(50_000, $this->walletBalance($customer));
    }

    public function test_paying_part_with_the_wallet_and_the_rest_at_the_counter(): void
    {
        [$customer, $token] = $this->customer();
        $this->adjustWallet($customer, 400_000)->assertCreated();
        $order = $this->customerOrder($token); // 650,000

        $this->postJson("/api/v1/customer/orders/{$order}/wallet-payment", [], $this->publicHeaders($this->auth($token)))->assertOk()
            ->assertJsonPath('data.method', 'wallet')
            ->assertJsonPath('data.amount', 400_000)
            ->assertJsonPath('order.payment_status', 'partially_paid')
            ->assertJsonPath('order.remaining_due', 250_000);
        $this->assertSame(0, $this->walletBalance($customer));

        // Nothing left in the wallet now.
        $this->postJson("/api/v1/customer/orders/{$order}/wallet-payment", [], $this->publicHeaders($this->auth($token)))->assertUnprocessable()->assertJsonPath('code', 'wallet_empty');

        $this->staff('POST', "/api/v1/orders/{$order}/payments", ['method' => 'cash', 'idempotency_key' => 'c1'])->assertCreated()->assertJsonPath('data.amount', 250_000);
        $this->staff('GET', "/api/v1/orders/{$order}")->assertJsonPath('data.payment_status', 'paid');
        $this->assertLedgersConsistent($customer);
    }

    public function test_a_wallet_that_covers_an_online_order_sends_it_to_the_kitchen(): void
    {
        Event::fake([OrderPlaced::class]);
        $this->enableOnline();
        [$customer, $token] = $this->customer();
        $this->adjustWallet($customer, 1_000_000);
        $order = $this->customerOrder($token, paymentMethod: 'online');

        $this->postJson("/api/v1/customer/orders/{$order}/wallet-payment", [], $this->publicHeaders($this->auth($token)))->assertOk()
            ->assertJsonPath('order.status', 'placed')
            ->assertJsonPath('order.payment_status', 'paid');

        Event::assertDispatchedTimes(OrderPlaced::class, 1);
        $this->assertSame(350_000, $this->walletBalance($customer));
    }

    public function test_wallet_payment_guards(): void
    {
        [$customer, $token] = $this->customer();
        [$other, $otherToken] = $this->customer('+989122222222');
        $this->adjustWallet($customer, 1_000_000);
        $order = $this->customerOrder($token);
        $guestOrder = $this->quickQrOrder()->json('data.id');

        // Someone else's order is invisible.
        $this->postJson("/api/v1/customer/orders/{$order}/wallet-payment", [], $this->publicHeaders($this->auth($otherToken)))->assertNotFound();
        // A guest order has nobody's wallet.
        $this->staff('POST', "/api/v1/orders/{$guestOrder}/wallet-payment", ['idempotency_key' => 'g'])->assertUnprocessable()->assertJsonPath('code', 'order_without_customer');

        $this->settings(['wallet.payments_enabled' => '0']);
        $this->postJson("/api/v1/customer/orders/{$order}/wallet-payment", [], $this->publicHeaders($this->auth($token)))->assertUnprocessable()->assertJsonPath('code', 'wallet_payments_disabled');
        $this->assertSame(0, $this->walletBalance($other));
    }

    public function test_staff_can_charge_the_wallet_with_a_limit_and_idempotently(): void
    {
        [$customer, $token] = $this->customer();
        $this->adjustWallet($customer, 1_000_000);
        $order = $this->customerOrder($token);

        $first = $this->staff('POST', "/api/v1/orders/{$order}/wallet-payment", ['amount' => 200_000, 'idempotency_key' => 'w1'])->assertCreated()->json('data.id');
        $this->staff('POST', "/api/v1/orders/{$order}/wallet-payment", ['amount' => 200_000, 'idempotency_key' => 'w1'])->assertOk()->assertJsonPath('data.id', $first);

        $this->assertSame(800_000, $this->walletBalance($customer));
    }

    public function test_cancelling_an_order_returns_wallet_money_to_the_wallet(): void
    {
        [$customer, $token] = $this->customer();
        $this->adjustWallet($customer, 1_000_000);
        $order = $this->customerOrder($token);
        $this->postJson("/api/v1/customer/orders/{$order}/wallet-payment", [], $this->publicHeaders($this->auth($token)))->assertOk();
        $this->assertSame(350_000, $this->walletBalance($customer));

        $this->staff('POST', "/api/v1/orders/{$order}/status", ['status' => 'cancelled', 'note' => 'تمام شد'])->assertOk();

        $this->assertSame(1_000_000, $this->walletBalance($customer));
        $this->staff('GET', "/api/v1/orders/{$order}")->assertJsonPath('data.payment_status', 'refunded')->assertJsonPath('data.needs_refund', false);
        $this->assertLedgersConsistent($customer);
    }

    public function test_a_wallet_payment_can_only_be_refunded_into_the_wallet(): void
    {
        [$customer, $token] = $this->customer();
        $this->adjustWallet($customer, 1_000_000);
        $order = $this->customerOrder($token);
        $payment = $this->postJson("/api/v1/customer/orders/{$order}/wallet-payment", [], $this->publicHeaders($this->auth($token)))->json('data.id');

        $this->staff('POST', "/api/v1/payments/{$payment}/refunds", ['amount' => 100_000, 'method' => 'cash', 'reason' => 'x', 'idempotency_key' => 'r1'])
            ->assertUnprocessable()->assertJsonPath('code', 'refund_wallet_method');
        $this->staff('POST', "/api/v1/payments/{$payment}/refunds", ['amount' => 100_000, 'method' => 'wallet', 'reason' => 'یک قهوه کم بود', 'idempotency_key' => 'r2'])->assertCreated();

        $this->assertSame(450_000, $this->walletBalance($customer));
        $this->assertLedgersConsistent($customer);
    }

    public function test_manual_adjustments_need_permission_a_reason_and_cannot_overdraw(): void
    {
        [$customer] = $this->customer();
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');

        $this->postJson("/api/v1/customers/{$customer->id}/wallet-adjustments", ['amount' => 1000, 'reason' => 'x', 'idempotency_key' => 'a'], $this->staffHeaders($cashier, $this->tenant))->assertForbidden();
        $this->staff('POST', "/api/v1/customers/{$customer->id}/wallet-adjustments", ['amount' => 1000, 'idempotency_key' => 'a'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->staff('POST', "/api/v1/customers/{$customer->id}/wallet-adjustments", ['amount' => 0, 'reason' => 'x', 'idempotency_key' => 'a'])->assertJsonValidationErrors('amount');

        $this->adjustWallet($customer, 100_000, 'جبران تأخیر')->assertCreated()->assertJsonPath('data.type_label', 'اصلاح دستی')->assertJsonPath('data.balance_after', 100_000);
        $this->adjustWallet($customer, -150_000)->assertUnprocessable()->assertJsonPath('code', 'wallet_insufficient');
        $this->staff('POST', "/api/v1/customers/{$customer->id}/points-adjustments", ['amount' => 50, 'reason' => 'هدیه', 'idempotency_key' => 'p'])->assertCreated();

        $this->assertSame(50, $this->points($customer));
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => AuditLog::query()->where('action', 'wallet.adjusted')->count()));
        $this->staff('GET', "/api/v1/customers/{$customer->id}/wallet-transactions")->assertJsonPath('data.0.description', 'جبران تأخیر');
    }
}
