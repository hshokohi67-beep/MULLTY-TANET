<?php

namespace App\Modules\Loyalty\Console;

use App\Modules\Core\Models\Tenant;
use App\Modules\Loyalty\Actions\GiveBirthdayGifts;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/** Scheduled hourly (routes/console.php); each tenant is handled in its own timezone from 09:00. */
final class BirthdayGiftsCommand extends Command
{
    protected $signature = 'loyalty:birthdays';

    protected $description = 'Give birthday gifts to customers whose Jalali birthday is today (once per Jalali year)';

    public function handle(TenantContext $context): int
    {
        Tenant::query()->where('status', 'active')->orderBy('id')->each(function (Tenant $tenant) use ($context): void {
            try {
                $count = $context->runAs($tenant, fn () => app(GiveBirthdayGifts::class)->handle());

                if ($count > 0) {
                    $this->line("{$tenant->slug}: {$count} birthday gift(s)");
                }
            } catch (Throwable $e) {
                report($e); // one tenant's problem must not stop the others
            }
        });

        return self::SUCCESS;
    }
}
