<?php

namespace App\Modules\Analytics\Console;

use App\Modules\Analytics\Support\DirtyDays;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Models\Tenant;
use App\Modules\Operations\Models\Expense;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Marks history for re-aggregation (first deploy, after a legacy import, or after changing how a
 * metric is computed). The scheduled rollup, or `--now`, then builds the rows.
 */
final class BackfillCommand extends Command
{
    protected $signature = 'analytics:backfill {--tenant= : Tenant slug (default: all)} {--days=400 : How far back} {--now : Roll up immediately}';

    protected $description = 'Mark past business days for re-aggregation';

    public function handle(TenantContext $context): int
    {
        $since = now()->subDays(max(1, (int) $this->option('days')))->toDateString();
        $slug = $this->option('tenant');

        Tenant::query()->when(is_string($slug) && $slug !== '', fn ($q) => $q->where('slug', $slug))->orderBy('id')->each(function (Tenant $tenant) use ($context, $since): void {
            $count = $context->runAs($tenant, function () use ($tenant, $since): int {
                $days = Order::query()->whereDate('business_date', '>=', $since)->distinct()->pluck('business_date')
                    ->merge(Expense::query()->whereDate('spent_on', '>=', $since)->distinct()->pluck('spent_on'))
                    ->map(fn ($d) => substr((string) $d, 0, 10))->unique();
                foreach ($days as $day) {
                    DirtyDays::mark($tenant->id, $day);
                }

                return $days->count();
            });
            $this->line("{$tenant->slug}: {$count} day(s) marked");
        });

        if ($this->option('now')) {
            $this->call('analytics:rollup', ['--limit' => 1000]);
        }

        return self::SUCCESS;
    }
}
