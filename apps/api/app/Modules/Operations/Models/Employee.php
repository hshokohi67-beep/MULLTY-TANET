<?php

namespace App\Modules\Operations\Models;

use App\Modules\Core\Support\TenantSettings;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A person on the payroll. Not necessarily a panel user; when linked (`user_id`), they can clock
 * in and out themselves.
 *
 * @property string $id
 * @property string $branch_id
 * @property ?string $user_id
 * @property string $name
 * @property ?string $phone_e164
 * @property ?string $position
 * @property string $pay_type hourly|monthly
 * @property int $rate rial per hour (hourly) or per month (monthly)
 * @property ?Carbon $hired_on
 * @property bool $is_active
 */
#[Fillable(['branch_id', 'user_id', 'name', 'phone_e164', 'position', 'pay_type', 'rate', 'hired_on', 'is_active'])]
class Employee extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['rate' => 'integer', 'hired_on' => 'date', 'is_active' => 'boolean'];
    }

    /** Rial per hour, so hourly and monthly staff cost the same way per worked minute. */
    public function hourlyRate(): float
    {
        return $this->pay_type === 'monthly'
            ? $this->rate / max(1, (int) TenantSettings::get('staff.monthly_hours'))
            : (float) $this->rate;
    }

    /** Labour cost in rial, rounded to a whole toman (fractions of a toman are noise on a payslip). */
    public function costOfMinutes(int $minutes): int
    {
        return (int) round($minutes / 60 * $this->hourlyRate() / 10) * 10;
    }
}
