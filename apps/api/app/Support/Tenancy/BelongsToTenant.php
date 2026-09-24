<?php

namespace App\Support\Tenancy;

use App\Modules\Core\Models\Tenant;
use App\Support\Database\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Makes a model tenant-owned:
 *  - every query is constrained to the current tenant (fail-closed),
 *  - tenant_id is filled from the context on create,
 *  - tenant_id can never be changed afterwards.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    // Tenant data follows the platform rule "the database stores UTC".
    use StoresDatesInUtc;

    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($model->getAttribute('tenant_id') === null) {
                // Platform code running in bypass mode may write tenant-less rows (e.g. platform audit logs).
                // Tables where tenant_id is NOT NULL still reject them at the database level.
                if ($context->has() || ! $context->isBypassed()) {
                    $model->setAttribute('tenant_id', $context->require()->getKey());
                }

                return;
            }

            if (! $context->isBypassed() && $context->id() !== $model->getAttribute('tenant_id')) {
                throw new LogicException(sprintf('Cannot create [%s] for another tenant.', $model::class));
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new LogicException(sprintf('tenant_id of [%s] is immutable.', $model::class));
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
