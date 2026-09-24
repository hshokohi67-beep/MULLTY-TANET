<?php

namespace Tests\Feature\Commerce;

use App\Modules\Core\Models\BranchOpeningHour;
use App\Modules\Core\Models\TenantSetting;
use App\Modules\Kitchen\Models\KitchenItem;
use App\Modules\Kitchen\Models\KitchenStation;
use Carbon\CarbonImmutable;

final class PreorderTest extends CommerceTestCase
{
    /** Saturday 2026-09-26, 09:07 Tehran; the branch opens 08:00–12:00 every day. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 09:07', 'Asia/Tehran'));

        $this->inTenant($this->tenant, function (): void {
            foreach (range(1, 7) as $day) {
                BranchOpeningHour::query()->create(['branch_id' => $this->branch->id, 'weekday' => $day, 'opens_at' => '08:00', 'closes_at' => '12:00']);
            }
            KitchenStation::query()->create(['branch_id' => $this->branch->id, 'name' => 'بار', 'is_default' => true]);
        });
    }

    private function setting(string $key, int $value): void
    {
        $this->inTenant($this->tenant, fn () => TenantSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]));
    }

    private function at(string $time, int $days = 0): string
    {
        return CarbonImmutable::parse("2026-09-26 {$time}", 'Asia/Tehran')->addDays($days)->toIso8601String();
    }

    /** @return array{0: string, 1: array<string, string>} a takeaway cart with one espresso and its auth header */
    private function takeawayCart(string $phone = '+989121111111'): array
    {
        [, $token] = $this->customer($phone);
        $auth = ['Authorization' => "Bearer {$token}"];
        $cart = $this->cart('takeaway', $auth);
        $this->addItem($cart, $this->variant($this->espresso), 1, [], $auth)->assertCreated();

        return [$cart, $auth];
    }

    public function test_slots_follow_hours_lead_time_and_max_days(): void
    {
        $data = $this->getJson("/api/v1/public/preorder-slots?branch_id={$this->branch->id}", $this->publicHeaders())->assertOk()->json('data');

        $this->assertTrue($data['open_now']);
        $this->assertSame(15, $data['slot_minutes']);
        // Today: 09:07 + 30 min lead → first slot 09:45; last slot 11:45 (closes at 12:00).
        $this->assertSame('امروز', $data['days'][0]['label']);
        $this->assertSame('۰۹:۴۵', $data['days'][0]['slots'][0]['label']);
        $this->assertSame('۱۱:۴۵', collect($data['days'][0]['slots'])->last()['label']);
        $this->assertCount(9, $data['days'][0]['slots']);
        $this->assertSame('فردا', $data['days'][1]['label']);
        $this->assertCount(16, $data['days'][1]['slots']);
        $this->assertCount(4, $data['days']); // today + 3 days

        // Settings change the grid.
        $this->setting('preorder.slot_minutes', 30);
        $this->setting('preorder.max_days', 0);
        $data = $this->getJson("/api/v1/public/preorder-slots?branch_id={$this->branch->id}", $this->publicHeaders())->json('data');
        $this->assertCount(1, $data['days']);
        $this->assertSame('۱۰:۰۰', $data['days'][0]['slots'][0]['label']);
    }

    public function test_checkout_rejects_too_soon_too_far_and_closed_times(): void
    {
        [$cart, $auth] = $this->takeawayCart();

        $this->checkout($cart, ['scheduled_for' => $this->at('09:20')], $auth)->assertStatus(422)->assertJsonPath('code', 'schedule_too_soon');
        $this->checkout($cart, ['scheduled_for' => $this->at('10:00', 5)], $auth)->assertStatus(422)->assertJsonPath('code', 'schedule_too_far');
        $this->checkout($cart, ['scheduled_for' => $this->at('13:00')], $auth)->assertStatus(422)->assertJsonPath('code', 'schedule_invalid');

        $this->checkout($cart, ['scheduled_for' => $this->at('10:30', 1)], $auth)->assertCreated()
            ->assertJsonPath('data.scheduled_for', CarbonImmutable::parse($this->at('10:30', 1))->utc()->toIso8601String())
            ->assertJsonPath('data.kitchen_release_at', CarbonImmutable::parse($this->at('10:10', 1))->utc()->toIso8601String());
    }

    public function test_slot_capacity_is_enforced_and_shown(): void
    {
        $this->setting('preorder.slot_capacity', 1);
        [$first, $auth] = $this->takeawayCart();
        $this->checkout($first, ['scheduled_for' => $this->at('10:05')], $auth)->assertCreated();

        $slots = collect($this->getJson("/api/v1/public/preorder-slots?branch_id={$this->branch->id}", $this->publicHeaders())->json('data.days.0.slots'))->keyBy('label');
        $this->assertFalse($slots['۱۰:۰۰']['available']);
        $this->assertTrue($slots['۱۰:۱۵']['available']);

        [$second, $auth2] = $this->takeawayCart('+989122222222');
        $this->checkout($second, ['scheduled_for' => $this->at('10:10')], $auth2)->assertStatus(422)->assertJsonPath('code', 'slot_full');
        $this->checkout($second, ['scheduled_for' => $this->at('10:15')], $auth2)->assertCreated();
    }

    public function test_preorders_reach_the_kitchen_shortly_before_their_slot(): void
    {
        [$cart, $auth] = $this->takeawayCart();
        $preorder = $this->checkout($cart, ['scheduled_for' => $this->at('11:00')], $auth)->assertCreated()->json('data.id');
        [$now, $auth2] = $this->takeawayCart('+989122222222');
        $asap = $this->checkout($now, [], $auth2)->assertCreated()->json('data.id');

        $items = fn (string $order) => $this->inTenant($this->tenant, fn () => KitchenItem::query()->where('order_id', $order)->count());
        $this->assertSame(1, $items($asap));      // an order for now goes straight to the kitchen
        $this->assertSame(0, $items($preorder));  // the pre-order waits

        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:39', 'Asia/Tehran'));
        $this->artisan('kitchen:release-preorders')->assertSuccessful();
        $this->assertSame(0, $items($preorder));

        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:40', 'Asia/Tehran'));
        $this->artisan('kitchen:release-preorders')->assertSuccessful();
        $this->assertSame(1, $items($preorder));

        // Idempotent.
        $this->artisan('kitchen:release-preorders')->assertSuccessful();
        $this->assertSame(1, $items($preorder));
    }

    public function test_upcoming_preorders_alert_and_not_late(): void
    {
        [$cart, $auth] = $this->takeawayCart();
        $this->checkout($cart, ['scheduled_for' => $this->at('09:45')], $auth)->assertCreated();

        $alerts = collect($this->getJson('/api/v1/dashboard/overview', $this->staffHeaders($this->owner, $this->tenant))->assertOk()->json('data.alerts'))->keyBy('type');
        $this->assertSame(1, $alerts['upcoming_preorders']['count']);

        // Placed 20 minutes ago but due in 18 minutes: not "late".
        $this->travelTo(CarbonImmutable::parse('2026-09-26 09:27', 'Asia/Tehran'));
        $alerts = collect($this->getJson('/api/v1/dashboard/overview', $this->staffHeaders($this->owner, $this->tenant))->json('data.alerts'))->keyBy('type');
        $this->assertArrayNotHasKey('late_orders', $alerts->all());
    }
}
