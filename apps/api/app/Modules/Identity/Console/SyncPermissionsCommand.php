<?php

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Actions\SyncPermissions;
use Illuminate\Console\Command;

final class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Sync the code-defined permission catalogue into the database';

    public function handle(SyncPermissions $sync): int
    {
        $sync->handle();
        $this->info('Permissions synced.');

        return self::SUCCESS;
    }
}
