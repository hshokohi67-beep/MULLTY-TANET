<?php

namespace App\Modules\Identity\Models;

use App\Support\Database\StoresDatesInUtc;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Staff / platform identity. One user may belong to several tenants (tenant_users).
 * Customers are NOT users; see App\Modules\Customers\Models\Customer.
 *
 * @property string $id
 * @property string $name
 * @property ?string $email
 * @property ?string $phone_e164
 * @property string $password
 * @property bool $is_platform_admin
 */
#[Fillable(['name', 'email', 'phone_e164', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, StoresDatesInUtc;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
