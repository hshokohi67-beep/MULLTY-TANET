<?php

namespace Tests\Feature\Insights;

use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Models\Branch;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Insights\Actions\SendDailyReport;
use App\Support\Sms\Providers\ArraySmsProvider;
use App\Support\Sms\SmsProvider;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Kitchen\KitchenTestCase;

final class DashboardWidgetsTest extends KitchenTestCase
{
    private function at(string $tehran): void
    {
        $this->travelTo(CarbonImmutable::parse($tehran, 'Asia/Tehran'));
    }

    private function widget(string $key, string $query = '', ?array $headers = null): TestResponse
    {
        return $this->getJson("/api/v1/dashboard/widgets/{$key}{$query}", $headers ?? $this->kds());
    }

    public function test_layouts_default_by_role_save_validate_and_reset(): void
    {
        $owner = $this->getJson('/api/v1/dashboard/layout', $this->kds())->assertOk();
        $owner->assertJsonPath('data.is_default', true)->assertJsonPath('data.widgets.0.key', 'kpis');
        $this->assertContains('club_liability', array_column($owner->json('data.catalog'), 'key'));

        $kitchen = $this->addMember($this->tenant, $this->owner, 'kitchen');
        $kh = $this->staffHeaders($kitchen, $this->tenant);
        $mine = $this->getJson('/api/v1/dashboard/layout', $kh)->json('data');
        $this->assertSame(['live', 'kitchen_speed', 'top_products', 'shift_notes'], array_column($mine['widgets'], 'key'));
        $this->assertNotContains('payment_mix', array_column($mine['catalog'], 'key'));

        // Forbidden widgets are dropped, bad sizes corrected, duplicates removed.
        $this->putJson('/api/v1/dashboard/layout', ['widgets' => [
            ['key' => 'payment_mix', 'size' => 'sm'],
            ['key' => 'live', 'size' => 'lg'],
            ['key' => 'live', 'size' => 'sm'],
            ['key' => 'heatmap', 'size' => 'lg'],
        ]], $kh)->assertOk()->assertJsonPath('data.widgets', [['key' => 'live', 'size' => 'sm'], ['key' => 'heatmap', 'size' => 'lg']]);
        $this->putJson('/api/v1/dashboard/layout', ['widgets' => [['key' => 'nope', 'size' => 'sm']]], $kh)->assertJsonValidationErrors('widgets.0.key');

        $this->assertSame(['live', 'heatmap'], array_column($this->getJson('/api/v1/dashboard/layout', $kh)->json('data.widgets'), 'key'));
        // The owner's layout is untouched by the kitchen user's.
        $this->getJson('/api/v1/dashboard/layout', $this->kds())->assertJsonPath('data.is_default', true);

        $this->deleteJson('/api/v1/dashboard/layout', [], $kh)->assertNoContent();
        $this->getJson('/api/v1/dashboard/layout', $kh)->assertJsonPath('data.is_default', true);

        $this->widget('payment_mix', '', $kh)->assertForbidden();
        $this->widget('nope')->assertNotFound();
    }

    public function test_channels_heatmap_goal_and_cancellations(): void
    {
        $this->settings(['goals.daily_sales' => '2000000', 'goals.monthly_sales' => '30000000']);
        $this->at('2026-09-22 09:30'); // Tuesday → heatmap day 3 (Saturday = 0)
        $this->quickQrOrder();
        $this->at('2026-09-23 18:10');
        $this->quickQrOrder();
        [, $token] = $this->customer();
        $this->customerOrder($token, 2);
        $gone = $this->quickQrOrder()->json('data.id');
        $this->staff('POST', "/api/v1/orders/{$gone}/status", ['status' => 'rejected', 'note' => 'تمام شد'])->assertOk();

        $channels = collect($this->widget('channel_mix')->assertOk()->json('data.channels'))->keyBy('type');
        $this->assertSame(1_300_000, $channels['takeaway']['sales']);
        $this->assertSame(1, $channels['qr_table']['orders']);

        $cells = collect($this->widget('heatmap')->json('data.cells'));
        $this->assertSame(intdiv(650_000, 4), $cells->first(fn ($c) => $c['day'] === 3 && $c['hour'] === 9)['sales']);
        $this->assertNotNull($cells->first(fn ($c) => $c['day'] === 4 && $c['hour'] === 18));

        $goal = $this->widget('goal')->json('data');
        $this->assertSame([2_000_000, 1_950_000], [$goal['daily_goal'], $goal['today']]);
        $this->assertSame(2_600_000, $goal['month_to_date']);

        $cancel = $this->widget('cancellations')->json('data');
        $this->assertSame([0, 1], [$cancel['cancelled'], $cancel['rejected']]);
        $this->assertSame('تمام شد', $cancel['reasons'][0]['reason']);
    }

    public function test_tables_kitchen_speed_branches_and_payment_health(): void
    {
        $this->at('2026-09-23 12:00');
        $order = $this->mixedOrder();
        [$latte, $espresso] = $this->items($order);
        $this->act($latte->id, 'start');
        $this->travel(4)->minutes();
        $this->act($latte->id, 'ready');
        $this->travel(10)->minutes(); // espresso ready 14 minutes after the order: late for the bar? no — the kitchen's threshold is 12
        $this->act($espresso->id, 'ready');

        $speed = collect($this->widget('kitchen_speed')->json('data.stations'))->keyBy('name');
        $this->assertSame(4, $speed['بار قهوه']['avg_minutes']);
        $this->assertEquals(1.0, $speed['آشپزخانه']['late_share']);

        $tables = $this->widget('tables_now')->json('data');
        $this->assertSame(1, $tables['occupied']);
        $this->assertSame(1, $tables['tables']);
        $this->staff('POST', "/api/v1/tables/{$this->table->id}/close-session")->assertNoContent();
        $this->assertSame(0, $this->widget('tables_now')->json('data.occupied'));

        $this->inTenant($this->tenant, fn () => Branch::query()->create(['name' => 'شعبه ونک', 'slug' => 'vanak']));
        $branches = $this->widget('branches')->json('data.branches');
        $this->assertCount(2, $branches);
        $this->assertSame(2_400_000, $branches[0]['sales']); // latte + almond milk (1,100,000) + two espressos (1,300,000)

        $this->enableOnline();
        [, $token] = $this->customer();
        $online = $this->customerOrder($token, paymentMethod: 'online');
        $this->postJson("/api/v1/public/orders/{$online}/pay", [], $this->publicHeaders(['X-Order-Token' => $this->inTenant($this->tenant, fn () => Order::query()->findOrFail($online)->trackingToken())]))->assertOk();
        $health = $this->widget('payment_health')->json('data');
        $this->assertSame([1, 1, 0], [$health['attempts'], $health['pending'], $health['paid']]);
    }

    public function test_club_widgets_discounts_and_at_risk_regulars(): void
    {
        [$regular, $token] = $this->customer('+989121000001');
        foreach (['2026-08-01', '2026-08-05', '2026-08-09'] as $day) { // a 4-day rhythm, then silence
            $this->at("{$day} 10:00");
            $this->complete($this->customerOrder($token));
        }
        $this->at('2026-09-23 12:00');
        $this->adjustWallet($regular, 300_000);
        $this->inTenant($this->tenant, fn () => Discount::query()->create(['name' => 'همیشگی', 'kind' => 'percent', 'value' => 1000, 'applies_to' => 'order']));
        $this->complete($this->quickQrOrder()->json('data.id'));

        $risk = $this->widget('at_risk')->json('data');
        $this->assertSame([1, 4, 1], [$risk['regulars'], $risk['typical_gap_days'], $risk['at_risk']]);
        $this->assertSame($regular->id, $risk['customers'][0]['customer_id']);

        $club = $this->widget('club_liability')->json('data');
        $this->assertSame(300_000, $club['wallet_owed']);
        $this->assertSame(1, $club['wallets']);

        $discounts = $this->widget('discounts')->json('data.discounts');
        $this->assertSame(['همیشگی', 1, 65_000, 585_000], [$discounts[0]['name'], $discounts[0]['uses'], $discounts[0]['given'], $discounts[0]['revenue']]);
    }

    public function test_shift_notes_are_shared_and_only_authors_delete(): void
    {
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $ch = $this->staffHeaders($cashier, $this->tenant);

        $id = $this->postJson('/api/v1/dashboard/shift-notes', ['body' => 'شیر بادام رو به اتمام است'], $ch)->assertCreated()->json('data.id');
        $this->widget('shift_notes')->assertJsonPath('data.notes.0.body', 'شیر بادام رو به اتمام است')->assertJsonPath('data.notes.0.author', 'عضو cashier');

        $waiter = $this->addMember($this->tenant, $this->owner, 'waiter');
        $this->deleteJson("/api/v1/dashboard/shift-notes/{$id}", [], $this->staffHeaders($waiter, $this->tenant))->assertForbidden();
        $this->deleteJson("/api/v1/dashboard/shift-notes/{$id}", [], $ch)->assertNoContent();
        $this->assertSame([], $this->widget('shift_notes')->json('data.notes'));
    }

    public function test_daily_report_is_sent_once_at_the_chosen_hour(): void
    {
        $this->settings(['reports.daily_sms' => '1', 'reports.daily_sms_hour' => '22']);
        $this->at('2026-09-23 12:00');
        $this->quickQrOrder();
        $send = fn (string $at) => $this->inTenant($this->tenant, fn () => app(SendDailyReport::class)->handle(CarbonImmutable::parse($at, 'Asia/Tehran')));

        $this->assertFalse($send('2026-09-23 21:59'));
        $this->assertTrue($send('2026-09-23 22:05'));
        $this->assertFalse($send('2026-09-23 22:40'));

        $sms = app(SmsProvider::class);
        $this->assertInstanceOf(ArraySmsProvider::class, $sms);
        $this->assertStringContainsString('فروش: ۶۵٬۰۰۰ تومان', $sms->messages[0]->text);
        $this->assertSame([$this->owner->phone_e164], $sms->messages[0]->recipients);

        $this->settings(['reports.daily_sms' => '0']);
        $this->assertFalse($send('2026-09-24 22:05'));
    }
}
