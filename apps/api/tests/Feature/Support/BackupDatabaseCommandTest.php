<?php

namespace Tests\Feature\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BackupDatabaseCommandTest extends TestCase
{
    public function test_it_refuses_a_non_mysql_connection(): void
    {
        config([
            'database.connections.not-mysql' => ['driver' => 'sqlite', 'database' => ':memory:'],
            'database.default' => 'not-mysql',
        ]);

        $this->assertSame(1, Artisan::call('backup:run'));
        $this->assertStringContainsString('only supports the mysql driver', Artisan::output());
    }

    public function test_it_dumps_gzips_uploads_and_prunes_old_backups(): void
    {
        if ((string) config('database.default') !== 'mysql') {
            $this->markTestSkipped('backup:run talks to a real mysqldump; this suite runs on sqlite.');
        }

        Storage::fake('local');
        Storage::disk('local')->put('backups/cafe-testing-20200101-000000.sql.gz', 'stale');

        $exit = Artisan::call('backup:run');

        $this->assertSame(0, $exit);
        $this->assertFalse(Storage::disk('local')->exists('backups/cafe-testing-20200101-000000.sql.gz'));

        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $path) => preg_match('/^backups\/cafe-testing-\d{8}-\d{6}\.sql\.gz$/', $path) === 1);
        $this->assertCount(1, $files);
        $this->assertGreaterThan(20, Storage::disk('local')->size($files->first()), 'The dump should contain real schema, not be empty.');
    }
}
