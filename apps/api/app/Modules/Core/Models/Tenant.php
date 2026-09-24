<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\TenantStatus;
use App\Support\Database\StoresDatesInUtc;
use App\Support\Money\CurrencyUnit;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A cafe/restaurant business. The root of all tenant-owned data.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 * @property string $timezone
 * @property string $locale
 * @property string $currency
 * @property CurrencyUnit $display_currency_unit
 */
#[Fillable(['name', 'slug', 'status', 'timezone', 'locale', 'currency', 'display_currency_unit'])]
#[UseFactory(TenantFactory::class)]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlids, StoresDatesInUtc;

    protected $attributes = [
        'status' => 'trial',
        'timezone' => 'Asia/Tehran',
        'locale' => 'fa',
        'currency' => 'IRR',
        'display_currency_unit' => 'toman',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'display_currency_unit' => CurrencyUnit::class,
        ];
    }

    /** @return HasMany<TenantDomain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    /** @return HasOne<TenantBranding, $this> */
    public function branding(): HasOne
    {
        return $this->hasOne(TenantBranding::class);
    }

    public function canOperate(): bool
    {
        return $this->status->canOperate();
    }
}
