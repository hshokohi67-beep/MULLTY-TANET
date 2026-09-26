<?php

namespace App\Modules\Messaging\Console;

use App\Modules\Messaging\Models\SmsLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** `sms:prune`: the send log keeps 180 days (it holds phone numbers). */
final class PruneSmsLogsCommand extends Command
{
    public const KEEP_DAYS = 180;

    protected $signature = 'sms:prune';

    protected $description = 'Delete SMS log rows older than 180 days';

    public function handle(TenantContext $context): int
    {
        // Platform maintenance across every café's log.
        $deleted = $context->bypass(fn () => SmsLog::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete());
        $this->info("Pruned {$deleted} SMS log row(s).");

        return self::SUCCESS;
    }
}
