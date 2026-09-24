<?php

namespace Tests\Feature\Loyalty;

use App\Modules\Core\Models\TenantSetting;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Loyalty\Models\Wallet;
use App\Modules\Loyalty\Models\WalletTransaction;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Payments\PaymentsTestCase;

/**
 * Club tests run on the commerce fixture (espresso 650,000 rial, latte 850,000/1,050,000 rial).
 * The club is enabled with 1 point per 100,000 rial, 1 point = 1,000 rial.
 */
abstract class ClubTestCase extends PaymentsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->settings(['loyalty.enabled' => '1']);
    }

    /** @param  array<string, string>  $values */
    protected function settings(array $values): void
    {
        $this->inTenant($this->tenant, function () use ($values): void {
            foreach ($values as $key => $value) {
                TenantSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'is_encrypted' => false]);
            }
        });
    }

    /** @return array<string, string> */
    protected function auth(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    /** A takeaway order by a signed-in customer; returns the order id. */
    protected function customerOrder(string $token, int $espressos = 1, string $paymentMethod = 'cash', ?string $variant = null): string
    {
        $cart = $this->cart('takeaway', $this->auth($token));
        $this->addItem($cart, $variant ?? $this->variant($this->espresso), $espressos, headers: $this->auth($token))->assertCreated();

        return $this->checkout($cart, ['payment_method' => $paymentMethod], $this->auth($token))->assertCreated()->json('data.id');
    }

    /** Moves an order through the kitchen to "completed" as the owner. */
    protected function complete(string $orderId): void
    {
        $headers = $this->staffHeaders($this->owner, $this->tenant);

        foreach (['accepted', 'preparing', 'ready', 'completed'] as $status) {
            $this->postJson("/api/v1/orders/{$orderId}/status", ['status' => $status], $headers)->assertOk();
        }
    }

    protected function staff(string $method, string $uri, array $body = []): TestResponse
    {
        return $this->json($method, $uri, $body, $this->staffHeaders($this->owner, $this->tenant));
    }

    protected function walletBalance(Customer $customer): int
    {
        return $this->inTenant($this->tenant, fn () => (int) Wallet::query()->where('customer_id', $customer->id)->value('balance'));
    }

    protected function points(Customer $customer): int
    {
        return $this->inTenant($this->tenant, fn () => (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('points'));
    }

    protected function lifetimeSpend(Customer $customer): int
    {
        return $this->inTenant($this->tenant, fn () => (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('lifetime_spend'));
    }

    /** balance = Σ ledger = last balance_after, for both ledgers. */
    protected function assertLedgersConsistent(Customer $customer): void
    {
        $this->inTenant($this->tenant, function () use ($customer): void {
            $wallet = Wallet::query()->where('customer_id', $customer->id)->first();
            if ($wallet !== null) {
                $rows = WalletTransaction::query()->where('wallet_id', $wallet->id)->orderBy('created_at')->orderBy('id')->get();
                $this->assertSame($wallet->balance, (int) $rows->sum('amount'), 'wallet balance = Σ amounts');
                $this->assertSame($wallet->balance, $rows->last()->balance_after ?? 0, 'wallet balance = last balance_after');
            }

            $account = LoyaltyAccount::query()->where('customer_id', $customer->id)->first();
            if ($account !== null) {
                $rows = LoyaltyTransaction::query()->where('account_id', $account->id)->orderBy('created_at')->orderBy('id')->get();
                $this->assertSame($account->points, (int) $rows->sum('points'), 'points = Σ ledger');
                $this->assertSame($account->points, $rows->last()->balance_after ?? 0, 'points = last balance_after');
            }
        });
    }

    protected function adjustWallet(Customer $customer, int $rials, string $reason = 'شارژ تست'): TestResponse
    {
        return $this->staff('POST', "/api/v1/customers/{$customer->id}/wallet-adjustments", ['amount' => $rials, 'reason' => $reason, 'idempotency_key' => uniqid('adj', true)]);
    }
}
