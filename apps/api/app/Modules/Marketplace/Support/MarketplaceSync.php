<?php

namespace App\Modules\Marketplace\Support;

use App\Modules\Core\Models\Tenant;
use App\Modules\Marketplace\Actions\ProjectStore;
use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Marketplace\Models\MarketplaceStore;
use App\Support\Tenancy\TenantContext;
use Throwable;

/**
 * Collects cafés whose public facts changed during a request (model events) and re-projects each
 * once when the request ends. Only cafés that have a listing (or rows to remove) do any work.
 */
final class MarketplaceSync
{
    /** @var array<string, true> */
    private array $pending = [];

    public function touch(?string $tenantId): void
    {
        if ($tenantId !== null) {
            $this->pending[$tenantId] = true;
        }
    }

    public function flush(): void
    {
        $ids = array_keys($this->pending);
        $this->pending = [];
        if ($ids === []) {
            return;
        }

        $context = app(TenantContext::class);
        // Platform-level check across the touched cafés: which of them take part at all.
        $relevant = $context->bypass(fn () => MarketplaceListing::query()->whereIn('tenant_id', $ids)->pluck('tenant_id')
            ->merge(MarketplaceStore::query()->whereIn('tenant_id', $ids)->distinct()->pluck('tenant_id'))->unique()->values());

        foreach (Tenant::query()->whereIn('id', $relevant)->get() as $tenant) {
            try {
                $context->runAs($tenant, fn () => app(ProjectStore::class)->handle());
            } catch (Throwable $e) {
                report($e); // the marketplace must never break the café's own request
            }
        }
    }
}
