<?php

namespace App\Modules\Messaging\Console;

use App\Modules\Messaging\Actions\RunSmsCampaigns;
use Illuminate\Console\Command;

/** `sms:campaigns`: sends due campaign chunks (every minute). */
final class SmsCampaignsCommand extends Command
{
    protected $signature = 'sms:campaigns';

    protected $description = 'Send due SMS campaign chunks (quiet hours and daily caps apply)';

    public function handle(RunSmsCampaigns $run): int
    {
        $this->info(sprintf('Sent %d campaign message(s).', $run->handle()));

        return self::SUCCESS;
    }
}
