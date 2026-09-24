<?php

namespace Tests\Feature\Loyalty;

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Models\Order;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Loyalty\Actions\SettleOrderRewards;
use App\Modules\Loyalty\Models\CashbackRule;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTier;
use Illuminate\Support\Facades\DB;

final class RewardsTest extends ClubTestCase
{
    private function tiers(): array
    {
        return $this->inTenant($this->tenant, fn () => [
            'bronze' => LoyaltyTier::query()->create(['name' => 'برنزی', 'min_spend' => 0]),
            'silver' => LoyaltyTier::query()->create(['name' => 'نقره‌ای', 'min_spend' => 2_000_000, 'points_multiplier' => 20_000]),
        ]);
    }

    public function test_points_cashback_and_spend_are_earned_on_completion_only(): void
    {
        $this->inTenant($this->tenant, fn () => CashbackRule::query()->create(['name' => '۵٪ همه‌چیز', 'kind' => 'percent', 'value' => 500, 'min_spend' => 1_000_000]));
        [$customer, $token] = $this->customer();
        $order = $this->customerOrder($token, 2); // 1,300,000

        $this->assertSame(0, $this->points($customer));
        $this->complete($order);

        $this->assertSame(13, $this->points($customer));          // 1 per 100,000 rial
        $this->assertSame(65_000, $this->walletBalance($customer)); // 5% cashback
        $this->assertSame(1_300_000, $this->lifetimeSpend($customer));
        $this->assertLedgersConsistent($customer);

        // Settling again (a replayed event) changes nothing.
        $this->inTenant($this->tenant, fn () => app(SettleOrderRewards::class)->handle(Order::query()->findOrFail($order)));
        $this->assertSame(13, $this->points($customer));
        $this->assertSame(65_000, $this->walletBalance($customer));
    }

    public function test_tiers_go_up_multiply_points_and_never_drop_on_refund(): void
    {
        ['silver' => $silver] = $this->tiers();
        [$customer, $token] = $this->customer();

        $first = $this->customerOrder($token, 4); // 2,600,000 → silver
        $this->complete($first);
        $this->assertSame(26, $this->points($customer)); // earned before the upgrade: x1
        $this->assertSame($silver->id, $this->inTenant($this->tenant, fn () => LoyaltyAccount::query()->where('customer_id', $customer->id)->value('tier_id')));

        $second = $this->customerOrder($token, 1); // 650,000 at silver x2
        $this->complete($second);
        $this->assertSame(26 + 12, $this->points($customer));

        // Refund the whole first order: points and spend come back out, the tier stays.
        $payment = $this->staff('POST', "/api/v1/orders/{$first}/payments", ['method' => 'cash', 'idempotency_key' => 'p1'])->json('data.id');
        $this->staff('POST', "/api/v1/payments/{$payment}/refunds", ['amount' => 2_600_000, 'method' => 'cash', 'reason' => 'اشتباه', 'idempotency_key' => 'r1'])->assertCreated();

        $this->assertSame(12, $this->points($customer));
        $this->assertSame(650_000, $this->lifetimeSpend($customer));
        $this->assertSame($silver->id, $this->inTenant($this->tenant, fn () => LoyaltyAccount::query()->where('customer_id', $customer->id)->value('tier_id')));
        $this->assertLedgersConsistent($customer);
    }

    public function test_a_partial_refund_reverses_rewards_proportionally_even_below_zero(): void
    {
        $this->inTenant($this->tenant, fn () => CashbackRule::query()->create(['name' => 'ثابت', 'kind' => 'fixed', 'value' => 100_000, 'min_spend' => 0]));
        [$customer, $token] = $this->customer();
        $order = $this->customerOrder($token, 2); // 1,300,000
        $payment = $this->staff('POST', "/api/v1/orders/{$order}/payments", ['method' => 'card_pos', 'idempotency_key' => 'p'])->json('data.id');
        $this->complete($order);
        $this->assertSame(100_000, $this->walletBalance($customer));

        // The customer spends the cashback, then half the order is refunded.
        $this->adjustWallet($customer, -100_000, 'مصرف');
        $this->staff('POST', "/api/v1/payments/{$payment}/refunds", ['amount' => 650_000, 'method' => 'card', 'reason' => 'نصف سفارش', 'idempotency_key' => 'r'])->assertCreated();

        $this->assertSame(6, $this->points($customer));              // 13 → 6
        $this->assertSame(-50_000, $this->walletBalance($customer)); // half the cashback clawed back
        $this->assertLedgersConsistent($customer);

        // A negative wallet can't pay.
        $next = $this->customerOrder($token);
        $this->postJson("/api/v1/customer/orders/{$next}/wallet-payment", [], $this->publicHeaders($this->auth($token)))->assertUnprocessable()->assertJsonPath('code', 'wallet_empty');
    }

    public function test_category_cashback_counts_only_that_category_including_subcategories(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $coffee = Category::query()->create(['name' => 'قهوه', 'slug' => 'coffee']);
            $milky = Category::query()->create(['name' => 'شیرقهوه', 'slug' => 'milky', 'parent_id' => $coffee->id]);
            DB::table('product_categories')->insert(['tenant_id' => $this->tenant->id, 'product_id' => $this->latte->id, 'category_id' => $milky->id, 'sort' => 0]);
            CashbackRule::query()->create(['name' => 'قهوه‌دوست', 'category_id' => $coffee->id, 'kind' => 'percent', 'value' => 1000, 'min_spend' => 0]);
        });
        [$customer, $token] = $this->customer();

        $cart = $this->cart('takeaway', $this->auth($token));
        $this->addItem($cart, $this->variant($this->latte), 1, [$this->milkOption('شیر معمولی')], $this->auth($token)); // 850,000 in "coffee"
        $this->addItem($cart, $this->variant($this->espresso), 1, headers: $this->auth($token));                         // 650,000, no category
        $order = $this->checkout($cart, [], $this->auth($token))->assertCreated()->json('data.id');
        $this->complete($order);

        $this->assertSame(85_000, $this->walletBalance($customer));
    }

    public function test_the_wallet_paid_part_earns_nothing(): void
    {
        [$customer, $token] = $this->customer();
        $this->adjustWallet($customer, 650_000);
        $order = $this->customerOrder($token, 2); // 1,300,000, half from the wallet
        $this->postJson("/api/v1/customer/orders/{$order}/wallet-payment", [], $this->publicHeaders($this->auth($token)))->assertOk();
        $this->complete($order);

        $this->assertSame(6, $this->points($customer)); // on 650,000 only
        $this->assertSame(650_000, $this->lifetimeSpend($customer));
    }

    public function test_nothing_is_earned_while_the_club_is_off_or_for_guests(): void
    {
        $this->settings(['loyalty.enabled' => '0']);
        [$customer, $token] = $this->customer();
        $this->complete($this->customerOrder($token));
        $this->complete($this->quickQrOrder()->json('data.id'));

        $this->assertSame(0, $this->points($customer));
        $this->assertSame(0, $this->inTenant($this->tenant, fn () => LoyaltyAccount::query()->count()));
    }

    public function test_redeeming_points_into_the_wallet(): void
    {
        $this->settings(['loyalty.min_redeem_points' => '10', 'loyalty.point_value' => '2000']);
        [$customer, $token] = $this->customer();
        $this->complete($this->customerOrder($token, 2)); // 13 points
        $headers = $this->publicHeaders($this->auth($token));

        $this->postJson('/api/v1/customer/points/redeem', ['points' => 5], $headers)->assertUnprocessable()->assertJsonPath('code', 'points_below_minimum');
        $this->postJson('/api/v1/customer/points/redeem', ['points' => 14], $headers)->assertUnprocessable()->assertJsonPath('code', 'points_insufficient');
        $this->postJson('/api/v1/customer/points/redeem', ['points' => '۱۰'], $headers)->assertOk()
            ->assertJsonPath('data.points', 3)
            ->assertJsonPath('data.wallet_balance', 20_000);

        $this->getJson('/api/v1/customer/points/transactions', $headers)->assertJsonPath('data.0.type', 'redeem')->assertJsonPath('data.0.amount', -10);
        $this->assertLedgersConsistent($customer);
    }

    public function test_club_summary_shows_progress_to_the_next_tier(): void
    {
        $this->tiers();
        [$customer, $token] = $this->customer();
        $this->complete($this->customerOrder($token));

        $this->getJson('/api/v1/customer/club', $this->publicHeaders($this->auth($token)))->assertOk()
            ->assertJsonPath('data.tier.name', 'برنزی')
            ->assertJsonPath('data.next_tier.name', 'نقره‌ای')
            ->assertJsonPath('data.next_tier.remaining', 1_350_000)
            ->assertJsonPath('data.points', 6)
            ->assertJsonPath('data.points_value', 6_000)
            ->assertJsonPath('data.program.enabled', true);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{7}$/', (string) $customer->fresh()?->referral_code);
    }

    public function test_a_discount_can_target_a_tier(): void
    {
        ['silver' => $silver] = $this->tiers();
        [$vip, $vipToken] = $this->customer();
        [, $otherToken] = $this->customer('+989123333333');
        $this->inTenant($this->tenant, fn () => LoyaltyAccount::for($vip)->forceFill(['tier_id' => $silver->id])->save());

        $this->staff('POST', '/api/v1/discounts', ['name' => 'ویژه‌ی نقره‌ای', 'kind' => 'percent', 'value' => 1000, 'applies_to' => 'order', 'rules' => ['tier_ids' => [$silver->id]]])->assertCreated();

        $vipCart = $this->cart('takeaway', $this->auth($vipToken));
        $this->addItem($vipCart, $this->variant($this->espresso), headers: $this->auth($vipToken))->assertJsonPath('data.quote.discount.amount', 65_000);

        $otherCart = $this->cart('takeaway', $this->auth($otherToken));
        $this->addItem($otherCart, $this->variant($this->espresso), headers: $this->auth($otherToken))->assertJsonPath('data.quote.discount', null);

        $this->assertSame(1, $this->inTenant($this->tenant, fn () => Discount::query()->first()?->rules()->where('rule_type', 'tier')->count()));
    }
}
