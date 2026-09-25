<?php

namespace App\Support\Observability;

use App\Support\Backup\Console\BackupDatabaseCommand;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Throwable;

/**
 * Turns `/up` from "the app booted" into "the app can actually serve a request" (DB + cache
 * reachable), and logs queries slow enough to be worth investigating outside a debugger.
 */
final class ObservabilityServiceProvider extends ServiceProvider
{
    /** Above this, a query is logged (not thrown) so a slow endpoint shows up without a profiler attached. */
    private const SLOW_QUERY_MS = 500;

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BackupDatabaseCommand::class]);
        }

        Event::listen(DiagnosingHealth::class, function (): void {
            try {
                DB::connection()->getPdo();
            } catch (Throwable $e) {
                throw new RuntimeException('Database is unreachable.', previous: $e);
            }

            try {
                Cache::store()->put('health:diagnosing', '1', 5);
            } catch (Throwable $e) {
                throw new RuntimeException('Cache is unreachable.', previous: $e);
            }
        });

        if (! $this->app->runningUnitTests()) {
            DB::listen(function (QueryExecuted $query): void {
                if ($query->time >= self::SLOW_QUERY_MS) {
                    Log::warning('Slow query', [
                        'sql' => $query->sql,
                        'time_ms' => $query->time,
                        'connection' => $query->connectionName,
                    ]);
                }
            });
        }
    }
}
