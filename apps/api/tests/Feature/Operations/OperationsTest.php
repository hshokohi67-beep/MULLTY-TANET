<?php

namespace Tests\Feature\Operations;

use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Tests\Feature\Commerce\CommerceTestCase;

/**
 * Saturday 2026-09-26 in Tehran. Sara (hourly, 1,000,000 rial/h, linked to a cashier account)
 * and Ali (monthly 192,000,000 rial = 1,000,000/h at 192 standard hours).
 */
final class OperationsTest extends CommerceTestCase
{
    private User $cashier;

    private string $sara;

    private string $ali;

    /** @return array<string, string> */
    private function h(?User $user = null): array
    {
        return $this->staffHeaders($user ?? $this->owner, $this->tenant);
    }

    private function at(string $time, int $days = 0): string
    {
        return CarbonImmutable::parse("2026-09-26 {$time}", 'Asia/Tehran')->addDays($days)->toIso8601String();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 07:30', 'Asia/Tehran'));
        $this->cashier = $this->addMember($this->tenant, $this->owner, 'cashier');

        $this->sara = $this->postJson('/api/v1/staff/employees', [
            'name' => 'سارا', 'position' => 'باریستا', 'branch_id' => $this->branch->id, 'user_id' => $this->cashier->id,
            'pay_type' => 'hourly', 'rate' => '۱۰۰۰۰۰۰', 'phone' => '۰۹۱۲۱۱۱۲۲۳۳',
        ], $this->h())->assertCreated()->assertJsonPath('data.rate', 1_000_000)->assertJsonPath('data.phone', '09121112233')->json('data.id');
        $this->ali = $this->postJson('/api/v1/staff/employees', [
            'name' => 'علی', 'branch_id' => $this->branch->id, 'pay_type' => 'monthly', 'rate' => 192_000_000,
        ], $this->h())->assertCreated()->assertJsonPath('data.hourly_rate', 1_000_000)->json('data.id');
    }

    public function test_shift_rules_and_copy_week(): void
    {
        $this->postJson('/api/v1/staff/shifts', ['employee_id' => $this->sara, 'starts_at' => $this->at('08:00'), 'ends_at' => $this->at('16:00')], $this->h())
            ->assertCreated()->assertJsonPath('data.minutes', 480);
        $this->postJson('/api/v1/staff/shifts', ['employee_id' => $this->sara, 'starts_at' => $this->at('15:00'), 'ends_at' => $this->at('18:00')], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'shift_overlap');
        $this->postJson('/api/v1/staff/shifts', ['employee_id' => $this->ali, 'starts_at' => $this->at('06:00'), 'ends_at' => $this->at('23:00')], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'shift_invalid');
        // Overnight is fine.
        $this->postJson('/api/v1/staff/shifts', ['employee_id' => $this->ali, 'starts_at' => $this->at('20:00'), 'ends_at' => $this->at('02:00', 1)], $this->h())->assertCreated();

        $this->assertCount(2, $this->getJson('/api/v1/staff/shifts?from=2026-09-26&to=2026-10-02', $this->h())->assertOk()->json('data'));

        $this->postJson('/api/v1/staff/shifts/copy-week', ['from' => '2026-09-26', 'to' => '2026-10-03'], $this->h())
            ->assertOk()->assertJsonPath('data.copied', 2)->assertJsonPath('data.skipped', 0);
        $this->postJson('/api/v1/staff/shifts/copy-week', ['from' => '2026-09-26', 'to' => '2026-10-03'], $this->h())
            ->assertOk()->assertJsonPath('data.copied', 0)->assertJsonPath('data.skipped', 2);
        $this->assertCount(2, $this->getJson('/api/v1/staff/shifts?from=2026-10-03&to=2026-10-09', $this->h())->json('data'));
    }

    public function test_self_clock_in_late_detection_and_payroll(): void
    {
        $this->postJson('/api/v1/staff/shifts', ['employee_id' => $this->sara, 'starts_at' => $this->at('08:00'), 'ends_at' => $this->at('16:00')], $this->h())->assertCreated();

        $this->getJson('/api/v1/time-clock/me', $this->h($this->cashier))->assertOk()->assertJsonPath('data.employee.name', 'سارا')->assertJsonPath('data.open', null);

        $this->travelTo(CarbonImmutable::parse('2026-09-26 08:15', 'Asia/Tehran'));
        $this->postJson('/api/v1/time-clock/in', [], $this->h($this->cashier))->assertCreated()->assertJsonPath('data.late_minutes', 15);
        $this->postJson('/api/v1/time-clock/in', [], $this->h($this->cashier))->assertStatus(422)->assertJsonPath('code', 'already_clocked_in');

        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:15', 'Asia/Tehran'));
        $this->postJson('/api/v1/time-clock/out', [], $this->h($this->cashier))->assertOk()->assertJsonPath('data.minutes', 240);
        $this->postJson('/api/v1/time-clock/out', [], $this->h($this->cashier))->assertStatus(422)->assertJsonPath('code', 'not_clocked_in');

        // A manager records Ali's four hours by hand.
        $this->postJson('/api/v1/staff/attendance', ['employee_id' => $this->ali, 'clock_in_at' => $this->at('07:00'), 'clock_out_at' => $this->at('11:00'), 'note' => 'کارت نزد'], $this->h())
            ->assertCreated()->assertJsonPath('data.source', 'manager')->assertJsonPath('data.edited', true);

        $payroll = $this->getJson('/api/v1/staff/payroll?from=2026-09-26&to=2026-09-26', $this->h())->assertOk()->json('data');
        $rows = collect($payroll['rows'])->keyBy('name');
        $this->assertSame(240, $rows['سارا']['minutes']);
        $this->assertSame(1, $rows['سارا']['late']);
        $this->assertSame(4_000_000, $rows['سارا']['cost']);
        $this->assertSame(4_000_000, $rows['علی']['cost']); // monthly spread over 192 h
        $this->assertSame(8_000_000, $payroll['total_cost']);

        // The owner isn't linked to an employee.
        $this->postJson('/api/v1/time-clock/in', [], $this->h())->assertStatus(403)->assertJsonPath('code', 'not_an_employee');
    }

    public function test_expenses_profit_labour_widgets_and_alerts(): void
    {
        $categories = $this->getJson('/api/v1/expense-categories', $this->h())->assertOk()->json('data');
        $this->assertCount(7, $categories); // defaults on first visit
        $rent = collect($categories)->firstWhere('name', 'اجاره')['id'];

        $this->postJson('/api/v1/expenses', ['branch_id' => $this->branch->id, 'category_id' => $rent, 'amount' => '۲۰۰۰۰۰۰', 'spent_on' => '2026-09-26', 'method' => 'transfer', 'payee' => 'صاحب‌خانه'], $this->h())
            ->assertCreated()->assertJsonPath('data.amount', 2_000_000)->assertJsonPath('data.category.name', 'اجاره');
        $this->postJson('/api/v1/expenses', ['branch_id' => $this->branch->id, 'category_id' => $rent, 'amount' => 1, 'spent_on' => '2027-01-01', 'method' => 'cash'], $this->h())
            ->assertUnprocessable()->assertJsonValidationErrors('spent_on'); // not in the future
        $this->getJson('/api/v1/expenses/summary?from=2026-09-01&to=2026-09-30', $this->h())->assertOk()
            ->assertJsonPath('data.total', 2_000_000)->assertJsonPath('data.categories.0.name', 'اجاره');
        $this->deleteJson("/api/v1/expense-categories/{$rent}", [], $this->h())->assertStatus(422)->assertJsonPath('code', 'category_in_use');

        // A sale (espresso 650,000) and four hours of Ali.
        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $cart = $this->cart('takeaway', $auth);
        $this->travelTo(CarbonImmutable::parse('2026-09-26 09:00', 'Asia/Tehran'));
        $this->addItem($cart, $this->variant($this->espresso), 1, [], $auth);
        $this->checkout($cart, [], $auth)->assertCreated();
        $this->postJson('/api/v1/staff/attendance', ['employee_id' => $this->ali, 'clock_in_at' => $this->at('07:00'), 'clock_out_at' => $this->at('09:00')], $this->h())->assertCreated();

        $profit = $this->getJson('/api/v1/dashboard/widgets/profit?range=today', $this->h())->assertOk()->json('data');
        $this->assertSame(650_000, $profit['sales']);
        $this->assertSame(2_000_000, $profit['labour']);
        $this->assertSame(2_000_000, $profit['expenses']);
        $this->assertSame(650_000 - 2_000_000 - 2_000_000, $profit['profit']);

        // Sara on shift now; a forgotten clock-out raises an alert after 16 hours.
        $this->postJson('/api/v1/time-clock/in', [], $this->h($this->cashier))->assertCreated();
        $labour = $this->getJson('/api/v1/dashboard/widgets/labour', $this->h())->assertOk()->json('data');
        $this->assertSame('سارا', $labour['on_shift'][0]['name']);
        $this->travel(17)->hours();
        $alert = collect($this->getJson('/api/v1/dashboard/alerts', $this->h())->json('data'))->firstWhere('type', 'open_attendance');
        $this->assertSame(1, $alert['count']);
    }

    public function test_permissions_and_linking_rules(): void
    {
        // The cashier may clock in but not manage staff or expenses.
        $this->getJson('/api/v1/staff/employees', $this->h($this->cashier))->assertForbidden();
        $this->getJson('/api/v1/expenses', $this->h($this->cashier))->assertForbidden();
        $this->getJson('/api/v1/time-clock/me', $this->h($this->cashier))->assertOk();

        // Linking: only a member of this café, and one account per employee.
        $stranger = User::factory()->create();
        $this->postJson('/api/v1/staff/employees', ['name' => 'غریبه', 'branch_id' => $this->branch->id, 'user_id' => $stranger->id, 'pay_type' => 'hourly', 'rate' => 1], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'user_not_member');
        $this->postJson('/api/v1/staff/employees', ['name' => 'تکراری', 'branch_id' => $this->branch->id, 'user_id' => $this->cashier->id, 'pay_type' => 'hourly', 'rate' => 1], $this->h())
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }
}
