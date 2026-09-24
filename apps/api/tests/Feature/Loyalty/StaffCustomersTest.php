<?php

namespace Tests\Feature\Loyalty;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTier;

final class StaffCustomersTest extends ClubTestCase
{
    public function test_list_search_filters_and_club_columns(): void
    {
        [$sara, $saraToken] = $this->customer('+989121234567');
        $this->inTenant($this->tenant, fn () => $sara->forceFill(['name' => 'سارا نیک‌نام', 'birth_month' => 7, 'birth_day' => 2])->save());
        [$ali] = $this->customer('+989357654321');
        $this->inTenant($this->tenant, fn () => $ali->forceFill(['name' => 'علی'])->save());
        $this->adjustWallet($sara, 300_000);
        $this->complete($this->customerOrder($saraToken));

        $all = $this->staff('GET', '/api/v1/customers')->assertOk()->json('data');
        $this->assertCount(2, $all);

        $row = collect($all)->firstWhere('id', $sara->id);
        $this->assertSame('09121234567', $row['phone']);
        $this->assertSame(300_000, $row['wallet_balance']);
        $this->assertSame(6, $row['points']);
        $this->assertSame(1, $row['orders_count']);

        $this->assertSame([$sara->id], array_column($this->staff('GET', '/api/v1/customers?q=۰۹۱۲۱۲۳')->json('data'), 'id'));
        $this->assertSame([$sara->id], array_column($this->staff('GET', '/api/v1/customers?q='.urlencode('نیک'))->json('data'), 'id'));
        $this->assertSame([$ali->id], array_column($this->staff('GET', '/api/v1/customers?q=7654')->json('data'), 'id'));
        $this->assertSame([$sara->id], array_column($this->staff('GET', '/api/v1/customers?birth_month=7')->json('data'), 'id'));

        $this->staff('GET', "/api/v1/customers/{$sara->id}")->assertOk()
            ->assertJsonPath('data.club.wallet_balance', 300_000)
            ->assertJsonPath('data.recent_orders.0.status', 'completed');
    }

    public function test_csv_export_is_excel_friendly_and_permissioned(): void
    {
        [$customer, $token] = $this->customer('+989121234567');
        $this->inTenant($this->tenant, fn () => $customer->forceFill(['name' => '=HYPERLINK("x")', 'birth_month' => 1, 'birth_day' => 5])->save());
        $this->adjustWallet($customer, 123_450);

        $response = $this->staff('GET', '/api/v1/customers/export')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('نام,موبایل,"تولد (ماه/روز)"', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);          // formula injection neutralised
        $this->assertStringContainsString('09121234567', $csv);
        $this->assertStringContainsString(',01/05,', $csv);
        $this->assertStringContainsString(',12345,', $csv);               // toman
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => AuditLog::query()->where('action', 'customers.exported')->count()));

        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $this->getJson('/api/v1/customers', $this->staffHeaders($cashier, $this->tenant))->assertOk();
        $this->getJson('/api/v1/customers/export', $this->staffHeaders($cashier, $this->tenant))->assertForbidden();
        $this->patchJson("/api/v1/customers/{$customer->id}", ['staff_note' => 'x'], $this->staffHeaders($cashier, $this->tenant))->assertForbidden();
    }

    public function test_program_settings_tiers_and_cashback_rules(): void
    {
        $settings = $this->staff('PUT', '/api/v1/loyalty/program', ['loyalty.points_per_100k' => 5, 'loyalty.point_value' => 500, 'wallet.payments_enabled' => false])->assertOk()->json('data.settings');
        $this->assertSame(5, $settings['loyalty.points_per_100k']);
        $this->assertFalse($settings['wallet.payments_enabled']);
        $this->staff('PUT', '/api/v1/loyalty/program', ['loyalty.point_value' => -1])->assertJsonValidationErrors('loyalty.point_value');

        $bronze = $this->staff('POST', '/api/v1/loyalty/tiers', ['name' => 'برنزی', 'min_spend' => 0])->assertCreated()->json('data.id');
        $this->staff('POST', '/api/v1/loyalty/tiers', ['name' => 'تکراری', 'min_spend' => 0])->assertJsonValidationErrors('min_spend');
        $this->staff('PUT', "/api/v1/loyalty/tiers/{$bronze}", ['name' => 'برنزی', 'min_spend' => 0, 'color' => '#CD7F32', 'points_multiplier' => 15_000])->assertOk()->assertJsonPath('data.points_multiplier', 15_000);

        $rule = $this->staff('POST', '/api/v1/loyalty/cashback-rules', ['name' => 'کش‌بک', 'kind' => 'percent', 'value' => 20_000, 'min_spend' => 0])->assertJsonValidationErrors('value');
        $rule = $this->staff('POST', '/api/v1/loyalty/cashback-rules', ['name' => 'کش‌بک', 'kind' => 'percent', 'value' => 500, 'min_spend' => 1_000_000])->assertCreated()->json('data.id');
        $this->staff('PUT', "/api/v1/loyalty/cashback-rules/{$rule}", ['name' => 'کش‌بک', 'kind' => 'fixed', 'value' => 50_000, 'min_spend' => 0, 'is_active' => false])->assertOk();

        $program = $this->staff('GET', '/api/v1/loyalty/program')->assertOk();
        $program->assertJsonPath('data.tiers.0.name', 'برنزی')->assertJsonPath('data.cashback_rules.0.is_active', false);

        // A tier with members can't be deleted.
        [$customer] = $this->customer();
        $this->inTenant($this->tenant, fn () => LoyaltyAccount::for($customer)->forceFill(['tier_id' => $bronze])->save());
        $this->staff('DELETE', "/api/v1/loyalty/tiers/{$bronze}")->assertUnprocessable()->assertJsonPath('code', 'tier_in_use');
        $this->staff('DELETE', "/api/v1/loyalty/cashback-rules/{$rule}")->assertNoContent();

        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $this->getJson('/api/v1/loyalty/program', $this->staffHeaders($cashier, $this->tenant))->assertForbidden();
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => LoyaltyTier::query()->count()));
    }
}
