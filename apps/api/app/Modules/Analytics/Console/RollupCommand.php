<?php

namespace App\Modules\Analytics\Console;

use App\Modules\Analytics\Actions\RollupDay;
use App\Modules\Analytics\Models\MetricDirtyDay;
use App\Modules\Core\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/** Scheduled every 5 minutes: rolls up every tenant's dirty business days (oldest first, capped per run). */
final class RollupCommand extends Command
{
    protected $signature = 'analytics:rollup {--limit=200 : Days per tenant per run}';

    protected $description = 'Re-aggregate business days whose orders, payments, costs, expenses or attendance changed';

    public function handle(TenantContext $context): int
    {
        $limit = max(1, (int) $this->option('limit'));

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($context, $limit): void {
            try {
                $done = $context->runAs($tenant, function () use ($limit): int {
                    $days = MetricDirtyDay::query()->orderBy('business_date')->limit($limit)->get()
                        ->map(fn (MetricDirtyDay $d) => $d->business_date->toDateString());
                    $rollup = app(RollupDay::class);
                    foreach ($days as $day) {
                        $rollup->handle($day);
                    }

                    return $days->count();
                });
                if ($done > 0) {
                    $this->line("{$tenant->slug}: {$done} day(s) rolled up");
                }
            } catch (Throwable $e) {
                report($e);
            }
        });

        return self::SUCCESS;
    }
}
