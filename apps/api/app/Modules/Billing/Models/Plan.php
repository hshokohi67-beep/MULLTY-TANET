<?php

namespace App\Modules\Billing\Models;

use App\Support\Database\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A platform plan (not tenant-owned). Prices in rial; `features` maps each catalogue key to a
 * bool (switch) or an int/null (limit, null = unlimited).
 *
 * @property string $id
 * @property string $key
 * @property string $name
 * @property ?string $tagline
 * @property int $monthly_price
 * @property int $yearly_price
 * @property array<string, bool|int|null> $features
 * @property bool $is_public
 * @property bool $is_trial_plan
 * @property int $sort
 */
#[Fillable(['key', 'name', 'tagline', 'monthly_price', 'yearly_price', 'features', 'is_public', 'is_trial_plan', 'sort'])]
class Plan extends Model
{
    use HasUlids, StoresDatesInUtc;

    protected function casts(): array
    {
        return ['monthly_price' => 'integer', 'yearly_price' => 'integer', 'features' => 'array', 'is_public' => 'boolean', 'is_trial_plan' => 'boolean', 'sort' => 'integer'];
    }

    public function price(string $cycle): int
    {
        return $cycle === 'yearly' ? $this->yearly_price : $this->monthly_price;
    }
}
