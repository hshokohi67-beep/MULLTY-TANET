<?php

namespace App\Modules\Marketplace\Console;

use App\Modules\Core\Models\Tenant;
use App\Modules\Marketplace\Actions\ProjectStore;
use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Marketplace\Models\MarketplaceStore;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Hourly: re-projects every café that takes part in the marketplace. Catches what no event
 * announces (a subscription running out, a feature expiring, popularity drifting).
 */
final class RefreshCommand extends Command
{
    protected $signature = 'marketplace:refresh';

    protected $description = 'Rebuild the public marketplace projection';

    public function handle(TenantContext $context): int
    {
        // Platform-wide job: which cafés take part (listed, or still have public rows to remove).
        $ids = $context->bypass(fn () => MarketplaceListing::query()->pluck('tenant_id')
            ->merge(MarketplaceStore::query()->distinct()->pluck('tenant_id'))->unique()->values());

        $shown = 0;
        foreach (Tenant::query()->whereIn('id', $ids)->get() as $tenant) {
            try {
                $shown += $context->runAs($tenant, fn () => app(ProjectStore::class)->handle()['eligible']) ? 1 : 0;
            } catch (Throwable $e) {
                report($e);
            }
        }
        $this->line("{$shown} cafe(s) in the marketplace");

        return self::SUCCESS;
    }
}
