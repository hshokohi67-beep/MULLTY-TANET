<?php

namespace App\Support\Backup\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Scheduled daily (routes/console.php). MySQL only: dumps, gzips, uploads to the backups disk
 * (local by default, an S3-compatible bucket in production via BACKUP_DISK), then prunes backups
 * older than the retention window using the date already in each backup's own filename.
 *
 * mysqldump writes to a file (never piped): a failed dump must fail the command, not leave an
 * empty archive that looks like a good backup.
 */
final class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Dump the database, gzip it, store it on the backups disk, and prune backups past the retention window';

    public function handle(): int
    {
        $connectionName = (string) (config('backup.connection') ?: config('database.default'));
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection) || ($connection['driver'] ?? null) !== 'mysql') {
            $this->error("backup:run only supports the mysql driver (connection \"{$connectionName}\").");

            return self::FAILURE;
        }

        $disk = Storage::disk((string) config('backup.disk'));
        $path = sprintf('backups/cafe-%s-%s.sql.gz', app()->environment(), now()->utc()->format('Ymd-His'));
        $sqlPath = (string) tempnam(sys_get_temp_dir(), 'backup-');
        $gzPath = $sqlPath.'.gz';

        try {
            $this->dump($connection, $sqlPath);
            $this->gzip($sqlPath, $gzPath);
            $this->upload($disk, $gzPath, $path);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($sqlPath);
            @unlink($gzPath);
        }

        $pruned = $this->prune($disk);
        $this->info(sprintf('Backed up to %s (pruned %d old backup(s)).', $path, $pruned));

        return self::SUCCESS;
    }

    /** @param  array<mixed>  $connection */
    private function dump(array $connection, string $sqlPath): void
    {
        $process = new Process([
            'mysqldump', '--single-transaction', '--quick', '--no-tablespaces',
            '-h', (string) ($connection['host'] ?? '127.0.0.1'),
            '-P', (string) ($connection['port'] ?? '3306'),
            '-u', (string) ($connection['username'] ?? ''),
            '--result-file='.$sqlPath,
            (string) ($connection['database'] ?? ''),
        ], null, [
            // The password travels as an env var, never on the command line (it would otherwise show in `ps`).
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ]);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump failed: '.trim($process->getErrorOutput()));
        }
        if (! is_file($sqlPath) || filesize($sqlPath) === 0) {
            throw new RuntimeException('mysqldump produced an empty dump.');
        }
    }

    /** Streams the dump into a gzip file (no shell pipe, no whole-file buffering). */
    private function gzip(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = gzopen($to, 'wb6');
        if ($in === false || $out === false) {
            throw new RuntimeException('Could not open the dump for compression.');
        }
        try {
            while (! feof($in)) {
                $chunk = fread($in, 1 << 20);
                if ($chunk === false || gzwrite($out, $chunk) === false) {
                    throw new RuntimeException('Compressing the dump failed.');
                }
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }

    private function upload(Filesystem $disk, string $gzPath, string $path): void
    {
        $stream = fopen($gzPath, 'r');

        if ($stream === false) {
            throw new RuntimeException('Could not open the dump file for upload.');
        }

        try {
            if (! $disk->put($path, $stream)) {
                throw new RuntimeException("Could not store the backup at {$path}.");
            }
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

            if ($madeAt !== null && $madeAt->lt($cutoff)) {
                $disk->delete($path);
                $pruned++;
            }
        }

        return $pruned;
    }
}
