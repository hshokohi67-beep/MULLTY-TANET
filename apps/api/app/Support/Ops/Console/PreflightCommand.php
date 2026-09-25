<?php

namespace App\Support\Ops\Console;

use App\Modules\Core\Models\Tenant;
use App\Modules\Identity\Models\User;
use Illuminate\Console\Command;

/**
 * Go-live checklist, run on the production server before opening the doors (and in CI against a
 * production-like env). Every failing *blocking* check makes the command exit non-zero.
 */
final class PreflightCommand extends Command
{
    protected $signature = 'ops:preflight {--json : Machine-readable output}';

    protected $description = 'Check that this installation is safe to run in production';

    /** Seeded demo data that must never reach production. */
    private const DEMO_EMAILS = ['admin@example.test'];

    private const DEMO_TENANTS = ['cafe-nemooneh', 'narenj', 'koohpayeh', 'eram', 'naghsh', 'yas', 'toranj', 'sabz'];

    public function handle(): int
    {
        $checks = $this->checks();
        $failed = array_filter($checks, fn (array $c) => ! $c['ok'] && $c['blocking']);

        if ($this->option('json')) {
            $this->line((string) json_encode(['ok' => $failed === [], 'checks' => $checks], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(['', 'Check', 'Fix'], array_map(fn (array $c) => [
                $c['ok'] ? 'OK' : ($c['blocking'] ? 'FAIL' : 'WARN'), $c['label'], $c['ok'] ? '' : $c['fix'],
            ], $checks));
            $failed === []
                ? $this->info('Ready for production.')
                : $this->error(count($failed).' blocking check(s) failed.');
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<array{key: string, label: string, ok: bool, blocking: bool, fix: string}> */
    public function checks(): array
    {
        $c = fn (string $key, string $label, bool $ok, string $fix, bool $blocking = true) => compact('key', 'label', 'ok', 'blocking', 'fix');
        $db = (string) config('database.default');
        $appUrl = (string) config('app.url');

        return [
            $c('env', 'APP_ENV is production', app()->environment('production'), 'Set APP_ENV=production.'),
            $c('debug', 'Debug mode is off', ! config('app.debug'), 'Set APP_DEBUG=false (debug pages leak secrets).'),
            $c('key', 'Application key is set', (string) config('app.key') !== '', 'Run php artisan key:generate once and keep the key safe.'),
            $c('https', 'APP_URL uses https', str_starts_with($appUrl, 'https://'), 'Serve the API over HTTPS and set APP_URL=https://…'),
            $c('storefront', 'STOREFRONT_URL uses https', str_starts_with((string) config('payments.storefront_url'), 'https://'), 'Set STOREFRONT_URL to the public https address (payment callbacks use it).'),
            $c('database', 'Database is not SQLite', config("database.connections.{$db}.driver") !== 'sqlite', 'Use MySQL (DB_CONNECTION=mysql).'),
            $c('queue', 'Queue is not sync', config('queue.default') !== 'sync', 'Use a real queue (QUEUE_CONNECTION=database or redis) and run a worker.'),
            $c('cache', 'Cache is shared (not array/file)', ! in_array(config('cache.default'), ['array', 'file'], true), 'Use CACHE_STORE=redis or database, so rate limits and locks work across servers.', false),
            $c('payments', 'Store payments use a real gateway', config('payments.driver') !== 'fake', 'Set PAYMENTS_DRIVER=zarinpal.'),
            $c('sandbox', 'Zarinpal sandbox is off', ! config('payments.gateways.zarinpal.sandbox'), 'Set ZARINPAL_SANDBOX=false.'),
            $c('billing', 'Subscription payments use a real gateway', config('billing.gateway') !== 'fake', 'Set BILLING_GATEWAY=zarinpal and the platform merchant id.'),
            $c('sms', 'SMS goes to a real provider', ! in_array(config('sms.default'), ['log', 'array'], true), 'Set SMS_PROVIDER=kavenegar and its API key (OTP codes are otherwise only logged).'),
            $c('proxies', 'Trusted proxies are configured', (string) config('app.trusted_proxies') !== '', 'Set TRUSTED_PROXIES to the web server/load balancer, or rate limits see one IP for everyone.'),
            $c('cookies', 'Session cookies are secure-only', (bool) config('session.secure'), 'Set SESSION_SECURE_COOKIE=true.'),
            $c('log', 'Logs go to a collectable channel', config('logging.default') !== 'single', 'Consider LOG_CHANNEL=json (stderr) for a log aggregator.', false),
            $c('backup', 'Backups go off the server', config('backup.disk') !== 'local', 'Point BACKUP_DISK at an S3-compatible bucket.', false),
            $c('storage', 'Public storage is linked', is_link(public_path('storage')) || is_dir(public_path('storage')), 'Run php artisan storage:link (or serve media from object storage).', config('filesystems.media_disk') === 'public'),
            $c('demo-users', 'No demo accounts', ! User::query()->whereIn('email', self::DEMO_EMAILS)->exists(), 'Delete admin@example.test and create your own platform admin.'),
            $c('demo-tenants', 'No demo cafés', ! Tenant::query()->whereIn('slug', self::DEMO_TENANTS)->exists(), 'Remove the demo cafés (never run DemoSeeder in production).'),
            $c('admin', 'A platform admin exists', User::query()->where('is_platform_admin', true)->whereNotIn('email', self::DEMO_EMAILS)->exists(), 'Create a platform admin (User::is_platform_admin).'),
        ];
    }
}
