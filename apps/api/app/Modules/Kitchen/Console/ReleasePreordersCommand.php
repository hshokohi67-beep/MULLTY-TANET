<?php

namespace App\Modules\Kitchen\Console;

use App\Modules\Kitchen\Actions\ReleasePreorders;
use Illuminate\Console\Command;

/** Scheduled every minute (routes/console.php): sends due pre-orders to the kitchen. */
final class ReleasePreordersCommand extends Command
{
    protected $signature = 'kitchen:release-preorders';

    protected $description = 'Route pre-orders to the kitchen shortly before their pickup/delivery slot';

    public function handle(ReleasePreorders $release): int
    {
        $count = $release->handle();
        if ($count > 0) {
            $this->line("{$count} pre-order(s) sent to the kitchen");
        }

        return self::SUCCESS;
    }
}
