<?php

namespace App\Modules\Discounts\Models;

use App\Modules\Discounts\Enums\DiscountKind;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property ?string $code
 * @property DiscountKind $kind
 * @property int $value
 * @property string $applies_to order | items
 * @property int $min_order
 * @property ?int $max_discount
 * @property ?Carbon $starts_at
 * @property ?Carbon $ends_at
 * @property ?array{weekdays?: list<int>, from?: string, to?: string} $schedule
 * @property ?int $usage_limit
 * @property ?int $per_customer_limit
 * @property int $used_count
 * @property bool $is_active
 * @property int $priority
 * @property Collection<int, DiscountRule> $rules
 */
#[Fillable([
    'name', 'code', 'kind', 'value', 'applies_to', 'min_order', 'max_discount', 'starts_at', 'ends_at',
    'schedule', 'usage_limit', 'per_customer_limit', 'is_active', 'priority',
])]
class Discount extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'kind' => DiscountKind::class,
            'value' => 'integer',
            'min_order' => 'integer',
            'max_discount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'schedule' => 'array',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Discount $discount): void {
            $discount->code = $discount->code !== null && trim($discount->code) !== '' ? mb_strtoupper(trim($discount->code)) : null;
        });
    }

    /** @return HasMany<DiscountRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(DiscountRule::class);
    }

    /** @return HasMany<DiscountUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(DiscountUsage::class);
    }

    public function isAutomatic(): bool
    {
        return $this->code === null;
    }
}
