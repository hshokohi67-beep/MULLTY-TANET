<?php

namespace Tests\Feature\Insights;

use App\Support\Localization\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Loyalty\ClubTestCase;

final class OverviewTest extends ClubTestCase
{
    private function at(string $tehran): void
    {
        $this->travelTo(CarbonImmutable::parse($tehran, 'Asia/Tehran'));
    }

    private function overview(string $query = '', ?array $headers = null): TestResponse
    {
        return $this->getJson('/api/v1/dashboard/overview'.$query, $headers ?? $this->staffHeaders($this->owner, $this->tenant))->assertOk();
    }

    public function test_today_is_compared_with_the_same_time_yesterday_and_last_week(): void
    {
        $this->at('2026-09-16 11:00'); // last week, same weekday
        $this->quickQrOrder();
        $this->at('2026-09-22 10:00'); // yesterday before "now"
        $this->quickQrOrder();
        $this->at('2026-09-22 18:00'); // yesterday after "now": not in the same-time comparison
        $this->quickQrOrder();
        $this->at('2026-09-23 12:05');
        $this->quickQrOrder();
        $this->quickQrOrder();
        $cancelled = $this->quickQrOrder()->json('data.id');
        $this->staff('POST', "/api/v1/orders/{$cancelled}/status", ['status' => 'cancelled', 'note' => 'x'])->assertOk();

        $data = $this->overview()->json('data');

        $this->assertSame(1_300_000, $data['kpis']['current']['sales']);
        $this->assertSame(2, $data['kpis']['current']['orders']);
        $this->assertSame(650_000, $data['kpis']['current']['average']);
        $this->assertSame(1, $data['kpis']['cancelled']);
        $this->assertSame(650_000, $data['kpis']['compare']['yesterday']['sales']);
        $this->assertSame(650_000, $data['kpis']['compare']['last_week']['sales']);

        // Hourly: today's 12:00 bucket, and the 4-week average reference at 11:00 (one order a week ago).
        $points = collect($data['series']['points'])->keyBy('label');
        $this->assertSame('hour', $data['series']['unit']);
        $this->assertSame(1_300_000, $points['12']['value']);
        $this->assertSame(intdiv(650_000, 4), $points['11']['reference']);

        $this->assertSame('اسپرسو', $data['top_products'][0]['name']);
        $this->assertSame(2, $data['top_products'][0]['quantity']);
        $this->assertCount(14, $data['trend']);
        $this->assertSame(1_300_000, $data['trend'][13]);
    }

    public function test_ranges_live_board_payments_and_alerts(): void
    {
        $this->at('2026-09-23 09:00');
        $order = $this->quickQrOrder()->json('data.id');
        $this->staff('POST', "/api/v1/orders/{$order}/payments", ['method' => 'cash', 'idempotency_key' => 'p'])->assertCreated();
        $this->postJson('/api/v1/public/tables/requests', ['type' => 'call_waiter'], $this->publicHeaders(['X-Table-Session' => $this->joinTable()]))->assertCreated();

        $this->at('2026-09-23 09:30'); // the order has now waited 30 minutes
        $data = $this->overview('?range=7d')->json('data');

        $this->assertSame('day', $data['series']['unit']);
        $this->assertCount(7, $data['series']['points']);
        $this->assertSame(1, $data['live']['new']);
        $this->assertSame(1, $data['live']['late']);
        $this->assertSame(1, $data['live']['table_calls']);
        $this->assertSame(650_000, collect($data['payment_mix'])->firstWhere('method', 'cash')['amount']);
        $this->assertSame(['late_orders', 'table_calls'], array_column($data['alerts'], 'type'));
        $this->assertSame('/dashboard/orders', $data['alerts'][0]['href']);
    }

    public function test_upcoming_birthdays_and_new_customers(): void
    {
        $this->at('2026-09-23 12:00');
        $tomorrow = JalaliDate::toJalali(CarbonImmutable::parse('2026-09-24 12:00', 'Asia/Tehran'));
        [$sara] = $this->customer('+989121111111');
        $this->inTenant($this->tenant, fn () => $sara->forceFill(['name' => 'سارا', 'birth_month' => $tomorrow['month'], 'birth_day' => $tomorrow['day']])->save());
        [$other] = $this->customer('+989122222222');
        $this->inTenant($this->tenant, fn () => $other->forceFill(['birth_month' => $tomorrow['month'] === 1 ? 6 : 1, 'birth_day' => 1])->save());

        $customers = $this->overview()->json('data.customers');

        $this->assertSame(2, $customers['new']);
        $this->assertCount(1, $customers['birthdays']);
        $this->assertSame(['سارا', 1], [$customers['birthdays'][0]['name'], $customers['birthdays'][0]['in_days']]);
    }

    public function test_sections_follow_permissions(): void
    {
        $kitchen = $this->addMember($this->tenant, $this->owner, 'kitchen');
        $data = $this->overview('', $this->staffHeaders($kitchen, $this->tenant))->json('data');

        $this->assertArrayHasKey('kpis', $data);
        $this->assertArrayNotHasKey('payment_mix', $data);
        $this->assertArrayNotHasKey('customers', $data);

        $this->getJson('/api/v1/dashboard/overview?range=forever', $this->staffHeaders($this->owner, $this->tenant))->assertJsonValidationErrors('range');
    }
}
