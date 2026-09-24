<?php

namespace App\Modules\Insights\Console;

use App\Modules\Core\Models\Tenant;
use App\Modules\Insights\Actions\SendDailyReport;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/** Scheduled hourly (routes/console.php); each tenant decides its own local hour. */
final class DailyReportCommand extends Command
{
    protected $signature = 'insights:daily-report';

    protected $description = 'Send the end-of-day SMS summary to owners of tenants that enabled it';

    public function handle(TenantContext $context): int
    {
        Tenant::query()->where('status', 'active')->orderBy('id')->each(function (Tenant $tenant) use ($context): void {
            try {
                if ($context->runAs($tenant, fn () => app(SendDailyReport::class)->handle())) {
                    $this->line("{$tenant->slug}: daily report sent");
                }
            } catch (Throwable $e) {
                report($e);
            }
        });

        return self::SUCCESS;
    }
}
