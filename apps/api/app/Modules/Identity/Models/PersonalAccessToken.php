<?php

namespace App\Modules\Identity\Models;

use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum token on ULID keys.
 *
 * The tokenable is loaded without the tenant scope so a customer token can be *resolved*
 * before any tenant check. Tenant binding is then enforced centrally in
 * IdentityServiceProvider (Sanctum::authenticateAccessTokensUsing): a customer token is only
 * valid when the request's tenant context matches the customer's tenant.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUlids;

    /** @return MorphTo<Model, $this> */
    public function tokenable(): MorphTo
    {
        return $this->morphTo('tokenable')->withoutGlobalScope(TenantScope::class);
    }
}
