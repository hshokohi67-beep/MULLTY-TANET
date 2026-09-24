<?php

namespace App\Modules\Customers\Models;

use App\Support\Tenancy\BelongsToTenant;
use App\Support\Tenancy\TenantBoundTokenable;
use Database\Factories\CustomerFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Tenant-scoped customer identity. The same phone at two tenants is two customers.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $phone_e164
 * @property ?string $name
 * @property ?int $birth_month Jalali
 * @property ?int $birth_day Jalali
 * @property bool $birthday_locked
 * @property ?string $referral_code
 * @property ?string $referred_by_id
 * @property ?Carbon $referred_at
 * @property ?Carbon $referral_rewarded_at
 * @property ?string $staff_note
 * @property bool $marketing_opt_in
 * @property ?Carbon $last_login_at
 * @property Carbon $created_at
 * @property ?Customer $referrer
 */
#[Fillable(['phone_e164', 'name', 'last_login_at', 'birth_month', 'birth_day', 'staff_note', 'marketing_opt_in'])]
#[UseFactory(CustomerFactory::class)]
class Customer extends Model implements AuthenticatableContract, TenantBoundTokenable
{
    /** @use HasFactory<CustomerFactory> */
    use Authenticatable, BelongsToTenant, HasApiTokens, HasFactory, HasUlids;

    /** No 0/O/1/I/L: codes are read aloud and typed on phones. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'birth_month' => 'integer',
            'birth_day' => 'integer',
            'birthday_locked' => 'boolean',
            'referred_at' => 'datetime',
            'referral_rewarded_at' => 'datetime',
            'marketing_opt_in' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            $customer->referral_code ??= self::newReferralCode();
        });
    }

    public function tokenTenantId(): string
    {
        return $this->tenant_id;
    }

    public function tokenIsActive(): bool
    {
        return true;
    }

    public function getAuthPassword(): string
    {
        return ''; // Customers authenticate with OTP only.
    }

    /** Customers created before the club existed get their code on first use. */
    public function ensureReferralCode(): string
    {
        for ($attempt = 0; $this->referral_code === null && $attempt < 5; $attempt++) {
            try {
                $this->forceFill(['referral_code' => self::newReferralCode()])->save();
            } catch (UniqueConstraintViolationException) {
                $this->referral_code = null;
            }
        }

        return (string) $this->referral_code;
    }

    public static function newReferralCode(): string
    {
        $code = '';
        for ($i = 0; $i < 7; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    public function hasBirthday(): bool
    {
        return $this->birth_month !== null && $this->birth_day !== null;
    }

    /** @return BelongsTo<Customer, $this> */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_id');
    }
}
