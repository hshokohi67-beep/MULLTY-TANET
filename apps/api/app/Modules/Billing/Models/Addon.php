<?php

namespace App\Modules\Billing\Models;

use App\Support\Database\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A platform add-on: raises a limit (+int per unit) or switches a feature on. Priced per month;
 * a yearly cycle is 10 × monthly, like plans.
 *
 * @property string $id
 * @property string $key
 * @property string $name
 * @property ?string $description
 * @property int $monthly_price
 * @property array<string, bool|int> $grants
 * @property ?list<string> $plans
 * @property bool $is_public
 * @property int $sort
 */
#[Fillable(['key', 'name', 'description', 'monthly_price', 'grants', 'plans', 'is_public', 'sort'])]
class Addon extends Model
{
    use HasUlids, StoresDatesInUtc;

    protected function casts(): array
    {
        return ['monthly_price' => 'integer', 'grants' => 'array', 'plans' => 'array', 'is_public' => 'boolean', 'sort' => 'integer'];
    }

    public function price(string $cycle): int
    {
        return $cycle === 'yearly' ? $this->monthly_price * 10 : $this->monthly_price;
    }

    public function availableFor(Plan $plan): bool
    {
        return $this->plans === null || in_array($plan->key, $this->plans, true);
    }
}
