<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $tenantId = $context->id() ?? throw new MissingTenantContextException(
            sprintf('Query on [%s] requires a tenant context.', $model::class),
        );

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
