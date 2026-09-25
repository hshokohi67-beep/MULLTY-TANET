<?php

namespace App\Support\Backup\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Scheduled daily (routes/console.php). MySQL only: dumps, gzips, uploads to the backups disk
 * (local by default, an S3-compatible bucket in production via BACKUP_DISK), then prunes backups
 * older than the retention window using the date already in each backup's own filename.
 */
final class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Dump the database, gzip it, store it on the backups disk, and prune backups past the retention window';

    public function handle(): int
    {
        $connectionName = (string) config('database.default');
        /** @var array<string, mixed> $connection */
        $connection = (array) config("database.connections.{$connectionName}");

        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->error("backup:run only supports the mysql driver (current default connection is \"{$connectionName}\").");

            return self::FAILURE;
        }

        $disk = Storage::disk((string) config('backup.disk'));
        $path = sprintf('backups/cafe-%s-%s.sql.gz', app()->environment(), now()->utc()->format('Ymd-His'));
        $tempPath = (string) tempnam(sys_get_temp_dir(), 'backup-');

        try {
            $this->dump($connection, $tempPath);
            $this->upload($disk, $tempPath, $path);
        } catch (ProcessFailedException $e) {
            $this->error('mysqldump failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($tempPath);
        }

        $pruned = $this->prune($disk);
        $this->info(sprintf('Backed up to %s (pruned %d old backup(s)).', $path, $pruned));

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $connection */
    private function dump(array $connection, string $tempPath): void
    {
        $command = sprintf(
            'mysqldump --single-transaction --quick --no-tablespaces -h %s -P %s -u %s %s | gzip > %s',
            escapeshellarg((string) $connection['host']),
            escapeshellarg((string) $connection['port']),
            escapeshellarg((string) $connection['username']),
            escapeshellarg((string) $connection['database']),
            escapeshellarg($tempPath),
        );

        // The password travels as an env var, never on the command line (it would otherwise show in `ps`).
        $process = Process::fromShellCommandline($command, null, ['MYSQL_PWD' => (string) ($connection['password'] ?? '')]);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    private function upload(Filesystem $disk, string $tempPath, string $path): void
    {
        $stream = fopen($tempPath, 'r');

        if ($stream === false) {
            throw new RuntimeException('Could not open the dump file for upload.');
        }

        try {
            $disk->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function prune(Filesystem $disk): int
    {
        $cutoff = now()->utc()->subDays((int) config('backup.keep_days'));
        $pruned = 0;

        foreach ($disk->files('backups') as $path) {
            if (preg_match('/-(\d{8}-\d{6})\.sql\.gz$/', basename($path), $matches) !== 1) {
                continue;
            }

            $madeAt = Carbon::createFromFormat('Ymd-His', $matches[1], 'UTC');

            if ($madeAt !== false && $madeAt->lt($cutoff)) {
                $disk->delete($path);
                $pruned++;
            }
        }

        return $pruned;
    }
}
