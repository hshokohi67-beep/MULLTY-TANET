<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Branch;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\AttendanceRecord;
use App\Modules\Operations\Models\Employee;
use App\Modules\Operations\Models\Expense;
use App\Modules\Operations\Models\ExpenseCategory;
use App\Modules\Operations\Models\Shift;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo operations: four employees (the demo cashier linked for self clock-in), this week's shift
 * plan, attendance for the days so far (one late arrival, one person still in), and two months
 * of running expenses, so the staff/expenses screens and the profit widget have data. Idempotent.
 */
class OperationsDemoSeeder extends Seeder
{
    public function run(TenantContext $context): void
    {
        $tz = $context->require()->timezone;
        if (Employee::query()->exists()) {
            return;
        }

        $branch = Branch::query()->where('slug', 'main')->firstOrFail();
        $cashier = User::query()->where('phone_e164', '+989120000002')->first();
        $t = fn (int $toman) => $toman * 10;

        $people = [
            Employee::query()->create(['name' => 'سارا محمدی', 'position' => 'صندوق', 'branch_id' => $branch->id, 'user_id' => $cashier?->id, 'pay_type' => 'hourly', 'rate' => $t(95_000), 'phone_e164' => '+989121112233']),
            Employee::query()->create(['name' => 'علی رضایی', 'position' => 'باریستا', 'branch_id' => $branch->id, 'pay_type' => 'hourly', 'rate' => $t(110_000)]),
            Employee::query()->create(['name' => 'مریم کاظمی', 'position' => 'آشپز', 'branch_id' => $branch->id, 'pay_type' => 'monthly', 'rate' => $t(22_000_000)]),
            Employee::query()->create(['name' => 'رضا احمدی', 'position' => 'پیک و خدمات', 'branch_id' => $branch->id, 'pay_type' => 'hourly', 'rate' => $t(80_000)]),
        ];
        // Morning / evening / kitchen / afternoon patterns.
        $pattern = [['08:00', '16:00'], ['15:00', '23:00'], ['10:00', '18:00'], ['12:00', '20:00']];

        $today = CarbonImmutable::now($tz)->startOfDay();
        $saturday = $today->subDays(($today->dayOfWeek + 1) % 7);

        foreach (range(0, 6) as $d) {
            $day = $saturday->addDays($d);
            foreach ($people as $i => $person) {
                // Everyone gets one day off a week, on a different day.
                if (($d + $i) % 7 === 6) {
                    continue;
                }
                [$from, $to] = $pattern[$i];
                $start = CarbonImmutable::parse("{$day->toDateString()} {$from}", $tz);
                $end = CarbonImmutable::parse("{$day->toDateString()} {$to}", $tz);
                $shift = Shift::query()->create(['employee_id' => $person->id, 'branch_id' => $branch->id, 'starts_at' => $start, 'ends_at' => $end]);

                // Attendance for shifts that have started: a little jitter, one late arrival, one person still in.
                if ($start->isFuture()) {
                    continue;
                }
                $late = $d === 1 && $i === 1 ? 22 : (($d * 7 + $i * 3) % 9) - 4;
                $in = $start->addMinutes($late);
                $out = $end->addMinutes((($d + $i) % 5) * 3);
                AttendanceRecord::query()->create([
                    'employee_id' => $person->id, 'branch_id' => $branch->id, 'shift_id' => $shift->id, 'source' => 'self',
                    'clock_in_at' => $in, 'clock_out_at' => $out->isFuture() ? null : $out,
                ]);
            }
        }

        $categories = [];
        foreach (ExpenseCategory::DEFAULTS as $i => $name) {
            $categories[$name] = ExpenseCategory::query()->create(['name' => $name, 'color' => $i % 4])->id;
        }
        $spend = function (string $category, int $toman, CarbonImmutable $on, string $method, ?string $payee = null, ?string $note = null) use ($categories, $branch, $t): void {
            if ($on->isFuture()) {
                return;
            }
            Expense::query()->create(['branch_id' => $branch->id, 'category_id' => $categories[$category], 'amount' => $t($toman), 'spent_on' => $on->toDateString(), 'method' => $method, 'payee' => $payee, 'note' => $note]);
        };
        foreach ([$today->subMonth(), $today] as $month) {
            $first = $month->startOfMonth();
            $spend('اجاره', 45_000_000, $first->addDays(1), 'transfer', 'صاحب‌خانه', 'اجاره‌ی ماهانه');
            $spend('قبوض', 3_200_000, $first->addDays(9), 'card', 'برق');
            $spend('قبوض', 900_000, $first->addDays(11), 'card', 'آب و گاز');
            $spend('تبلیغات', 2_500_000, $first->addDays(5), 'transfer', 'اینستاگرام');
            $spend('متفرقه', 650_000, $first->addDays(14), 'cash', null, 'مواد شوینده');
        }
        $spend('تعمیرات و نگهداری', 4_800_000, $today->subDays(3), 'cash', 'تعمیرکار دستگاه اسپرسو', 'تعویض واشر گروپ‌هد');
        $spend('حمل و نقل', 380_000, $today->subDays(1), 'cash', 'اسنپ باکس');
    }
}
