<?php

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Actions\RollupDay;
use App\Modules\Analytics\Models\DailyMetric;
use App\Modules\Analytics\Models\MetricDirtyDay;
use App\Modules\Analytics\Support\DirtyDays;
use App\Modules\Core\Models\Branch;
use App\Modules\Inventory\Actions\ManageStock;
use App\Modules\Inventory\Enums\StockMovementType;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Payments\PaymentsTestCase;
use ZipArchive;

/** Saturday 2026-09-26, 10:00 Tehran unless a test travels. Espresso 650,000 / latte (small) 850,000 rial. */
final class ReportsTest extends PaymentsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:00', 'Asia/Tehran'));
    }

    /** @return array<string, string> */
    private function h(): array
    {
        return $this->staffHeaders($this->owner, $this->tenant);
    }

    private function report(string $name, string $query = 'from=2026-09-26&to=2026-09-26'): TestResponse
    {
        return $this->getJson("/api/v1/reports/{$name}?{$query}", $this->h())->assertOk();
    }

    private function takeawayLatte(): string
    {
        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $cart = $this->cart('takeaway', $auth);
        $this->addItem($cart, $this->variant($this->latte), 1, [$this->milkOption('شیر معمولی')], $auth)->assertCreated();

        return $this->checkout($cart, [], $auth)->assertCreated()->json('data.id');
    }

    public function test_rollup_matches_orders_and_absorbs_late_cancellations_and_refunds(): void
    {
        $a = $this->quickQrOrder()->json('data.id');
        $b = $this->quickQrOrder()->json('data.id');
        $this->takeawayLatte();
        $payment = $this->postJson("/api/v1/orders/{$a}/payments", ['idempotency_key' => 'p1', 'method' => 'cash'], $this->h())->assertCreated()->json('data.id');

        $s = $this->report('summary')->json('data');
        $this->assertSame(3, $s['totals']['orders']);
        $this->assertSame(2_150_000, $s['totals']['sales']);
        $this->assertSame(3, $s['totals']['items']);
        $this->assertSame(1, $s['totals']['buyers']);
        $this->assertSame(['qr_table' => 1_300_000, 'takeaway' => 850_000], collect($s['channels'])->pluck('sales', 'key')->all());
        $this->assertSame([['key' => 'cash', 'label' => 'نقدی', 'amount' => 650_000]], $s['payments']);
        $this->assertFalse($s['stale']);

        // A day later: one order is cancelled and part of a payment refunded. Both land on the 26th.
        $this->travelTo(CarbonImmutable::parse('2026-09-27 09:00', 'Asia/Tehran'));
        $this->postJson("/api/v1/orders/{$b}/status", ['status' => 'cancelled', 'note' => 'مشتری رفت'], $this->h())->assertOk();
        $this->postJson("/api/v1/payments/{$payment}/refunds", ['idempotency_key' => 'r1', 'amount' => 200_000, 'method' => 'cash', 'reason' => 'اشتباه'], $this->h())->assertCreated();

        $t = $this->report('summary')->json('data.totals');
        $this->assertSame(2, $t['orders']);
        $this->assertSame(1, $t['cancelled']);
        $this->assertSame(1_500_000, $t['sales']);
        $this->assertSame(200_000, $t['refunds']);
        $this->assertSame(1_300_000, $t['net_sales']);
        $this->assertSame([['key' => 'cash', 'label' => 'نقدی', 'amount' => 450_000]], $this->report('summary')->json('data.payments'));

        // The dashboard reads the same aggregates.
        $this->getJson('/api/v1/dashboard/overview?range=yesterday', $this->h())->assertOk()
            ->assertJsonPath('data.kpis.current.sales', 1_500_000)->assertJsonPath('data.kpis.current.orders', 2);

        // Idempotent: rolling the day up again changes nothing.
        $this->inTenant($this->tenant, function (): void {
            app(RollupDay::class)->handle('2026-09-26');
            app(RollupDay::class)->handle('2026-09-26');
            $this->assertSame(1, DailyMetric::query()->whereDate('business_date', '2026-09-26')->count());
            $this->assertSame(1_500_000, (int) DailyMetric::query()->sum('sales'));
        });
    }

    public function test_products_hours_branches_comparison_and_exports(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-19 10:00', 'Asia/Tehran'));
        $this->quickQrOrder();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:20', 'Asia/Tehran'));
        $this->quickQrOrder();
        $this->quickQrOrder();
        $this->takeawayLatte();
        $this->inTenant($this->tenant, fn () => Branch::query()->create(['name' => 'شعبه دو', 'slug' => 'two']));

        $week = 'from=2026-09-20&to=2026-09-26';
        $s = $this->report('summary', $week.'&compare=previous')->json('data');
        $this->assertSame('2026-09-13', $s['compare']['from']);
        $this->assertSame(2_150_000, $s['totals']['sales']);
        $this->assertSame(650_000, $s['previous']['sales']);
        $this->assertSame('day', $s['series']['unit']);
        $this->assertCount(7, $s['series']['points']);
        $this->assertSame(['value' => 2_150_000, 'reference' => 650_000], array_intersect_key(end($s['series']['points']), ['value' => 0, 'reference' => 0]));

        // Beyond 62 days the series is bucketed by Jalali month.
        $this->assertSame('month', $this->report('summary', 'from=2026-06-01&to=2026-09-26')->json('data.series.unit'));

        $p = $this->report('products', $week)->json('data');
        $this->assertSame(['اسپرسو', 'لاته'], array_column($p['products'], 'name'));
        $this->assertSame([2, 1_300_000, 'A'], [$p['products'][0]['quantity'], $p['products'][0]['revenue'], $p['products'][0]['class']]);
        $this->assertSame(['A', 'A'], array_column($p['products'], 'class')); // latte starts at 60% of revenue, inside the top 80%
        $this->assertSame([['name' => 'بدون دسته', 'quantity' => 3, 'revenue' => 2_150_000, 'share' => 1]], $p['categories']);

        // Saturday (day 0) at 10:00 — one Saturday in the range, so the averages are the totals.
        $cell = collect($this->report('hours', $week)->json('data.cells'))->firstWhere('hour', 10);
        $this->assertSame([0, 2_150_000, 3], [$cell['day'], $cell['sales'], (int) $cell['orders']]);

        $branches = $this->report('branches', $week)->json('data.branches');
        $this->assertSame(['شعبه مرکزی', 'شعبه دو'], array_column($branches, 'name'));
        $this->assertSame([1, 0], array_column($branches, 'share'));

        // Exports: CSV with a BOM and Persian headers; XLSX as a right-to-left sheet.
        $csv = $this->get("/api/v1/reports/export?{$week}&report=products&format=csv", $this->h())->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('محصول,دسته,تعداد', $csv);
        $this->assertStringContainsString('اسپرسو,"بدون دسته",2,130000,60.5', $csv);

        $xlsx = $this->get("/api/v1/reports/export?{$week}&report=daily&format=xlsx", $this->h())->assertOk()->streamedContent();
        $this->assertStringStartsWith('PK', $xlsx);
        $file = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents((string) $file, $xlsx);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open((string) $file));
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink((string) $file);
        $this->assertStringContainsString('rightToLeft="1"', $sheet);
        $this->assertStringContainsString('۱۴۰۵/۰۷/۰۴', $sheet); // 2026-09-26 as Jalali text
        $this->assertStringContainsString('<v>215000</v>', $sheet);  // toman, numeric
    }

    public function test_labour_expenses_and_waste_feed_profit_and_side_reports(): void
    {
        $this->quickQrOrder();
        $this->takeawayLatte();

        $this->inTenant($this->tenant, function (): void {
            $stock = app(ManageStock::class);
            $beans = $stock->saveIngredient(['name' => 'قهوه', 'unit' => 'g', 'cost_per_big_unit' => 1_000_000, 'low_stock_threshold' => 0]);
            $stock->adjust($beans, $this->branch->id, StockMovementType::Waste, 500, 'g', 'ریخت', $this->owner->id); // 500 g × 1,000,000/kg
        });
        $category = $this->getJson('/api/v1/expense-categories', $this->h())->json('data.0.id');
        $this->postJson('/api/v1/expenses', ['branch_id' => $this->branch->id, 'category_id' => $category, 'amount' => 100_000, 'spent_on' => '2026-09-26', 'method' => 'cash'], $this->h())->assertCreated();
        $employee = $this->postJson('/api/v1/staff/employees', ['name' => 'علی', 'branch_id' => $this->branch->id, 'pay_type' => 'hourly', 'rate' => 200_000], $this->h())->json('data.id');
        $this->postJson('/api/v1/staff/attendance', [
            'employee_id' => $employee,
            'clock_in_at' => CarbonImmutable::parse('2026-09-26 07:00', 'Asia/Tehran')->toIso8601String(),
            'clock_out_at' => CarbonImmutable::parse('2026-09-26 09:00', 'Asia/Tehran')->toIso8601String(),
        ], $this->h())->assertCreated();

        $t = $this->report('summary')->json('data.totals');
        $this->assertSame([1_500_000, 400_000, 100_000, 500_000], [$t['sales'], $t['labour'], $t['expenses'], $t['waste']]);
        $this->assertSame(1_500_000 - 400_000 - 100_000 - 500_000, $t['profit']);

        // An expense moved to another day leaves this one.
        $expense = $this->getJson('/api/v1/expenses?from=2026-09-26&to=2026-09-26', $this->h())->json('data.0.id');
        $this->putJson("/api/v1/expenses/{$expense}", ['branch_id' => $this->branch->id, 'category_id' => $category, 'amount' => 100_000, 'spent_on' => '2026-09-25', 'method' => 'cash'], $this->h())->assertOk();
        $this->assertSame(0, $this->report('summary')->json('data.totals.expenses'));

        $c = $this->report('customers')->json('data');
        $this->assertSame(1, $c['buyers']);
        $this->assertSame('0912***1111', $c['top'][0]['phone']);
        $this->assertSame(850_000, $c['top'][0]['spend']);

        $inv = $this->report('inventory')->json('data');
        $this->assertSame(500_000, $inv['waste']);
        $this->assertSame([['note' => 'ریخت', 'count' => 1]], $inv['reasons']);
        $this->assertSame('قهوه', $inv['waste_items'][0]['name']);
    }

    public function test_permissions_validation_backlog_and_scheduler(): void
    {
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $this->getJson('/api/v1/reports/summary', $this->staffHeaders($cashier, $this->tenant))->assertForbidden();
        $manager = $this->addMember($this->tenant, $this->owner, 'manager');
        $this->getJson('/api/v1/reports/summary', $this->staffHeaders($manager, $this->tenant))->assertOk()
            ->assertJsonPath('data.period.to', '2026-09-26')->assertJsonPath('data.period.days', 30);

        $this->getJson('/api/v1/reports/summary?from=2025-01-01&to=2026-09-26', $this->h())->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson('/api/v1/reports/export?format=pdf', $this->h())->assertUnprocessable()->assertJsonValidationErrors('format');

        // A large backlog (e.g. after an import) is rolled up partly on read, the rest by the scheduler.
        $this->inTenant($this->tenant, function (): void {
            for ($i = 0; $i < 40; $i++) {
                DirtyDays::mark($this->tenant->id, CarbonImmutable::parse('2026-08-01')->addDays($i));
            }
        });
        $this->report('summary', 'from=2026-08-01&to=2026-09-26')->assertJsonPath('data.stale', true);
        $this->artisan('analytics:rollup')->assertSuccessful();
        $this->assertSame(0, $this->inTenant($this->tenant, fn () => MetricDirtyDay::query()->count()));
        $this->report('summary', 'from=2026-08-01&to=2026-09-26')->assertJsonPath('data.stale', false);
    }
}
