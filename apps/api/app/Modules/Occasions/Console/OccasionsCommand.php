<?php

namespace App\Modules\Occasions\Console;

use App\Modules\Core\Models\Tenant;
use App\Modules\Occasions\Actions\NotifyUpcomingOccasions;
use App\Modules\Occasions\Actions\ResolveOccasionDates;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Daily (routes/console.php): resolve this and next year's dates, then tell every café about
 * occasions that just entered their lead time.
 */
final class OccasionsCommand extends Command
{
    protected $signature = 'occasions:run {--resolve-only : Only (re)compute dates}';

    protected $description = 'Resolve occasion dates and notify cafés about upcoming occasions';

    public function handle(TenantContext $context, ResolveOccasionDates $resolve): int
    {
        $this->line('dates changed: '.$resolve->handle());
        if ($this->option('resolve-only')) {
            return self::SUCCESS;
        }

        Tenant::query()->whereIn('status', ['active', 'trial'])->orderBy('id')->each(function (Tenant $tenant) use ($context): void {
            try {
                $count = $context->runAs($tenant, fn () => app(NotifyUpcomingOccasions::class)->handle());
                if ($count > 0) {
                    $this->line("{$tenant->slug}: {$count} occasion notice(s)");
                }
            } catch (Throwable $e) {
                report($e); // one café's problem must not stop the others
            }
        });

        return self::SUCCESS;
    }
}
