<?php

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Actions\RunRenewals;
use App\Modules\Core\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/** Scheduled daily: renewal invoices and owner reminders for every café. */
final class RenewalsCommand extends Command
{
    protected $signature = 'billing:renewals';

    protected $description = 'Issue renewal invoices and send subscription reminders';

    public function handle(TenantContext $context): int
    {
        Tenant::query()->whereIn('status', ['trial', 'active'])->orderBy('id')->each(function (Tenant $tenant) use ($context): void {
            try {
                $out = $context->runAs($tenant, fn () => app(RunRenewals::class)->handle());
                if ($out['invoice'] || $out['reminder']) {
                    $this->line("{$tenant->slug}: ".($out['invoice'] ? 'renewal invoice ' : '').($out['reminder'] ? "reminder {$out['reminder']}" : ''));
                }
            } catch (Throwable $e) {
                report($e);
            }
        });

        return self::SUCCESS;
    }
}
