<?php

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Actions\ImportWooCommerceCatalog;
use App\Modules\Catalog\Support\WooCommerce\WooCommerceReader;
use App\Modules\Core\Models\Tenant;
use App\Support\Money\CurrencyUnit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Example:
 *   php artisan catalog:import-woocommerce cafe-nemooneh --connection=legacy_wp --prefix=wp_ --dry-run
 * The source connection must be defined in config/database.php (see the "legacy_wp" entry).
 */
final class ImportWooCommerceCatalogCommand extends Command
{
    protected $signature = 'catalog:import-woocommerce
        {tenant : Tenant slug}
        {--connection=legacy_wp : Database connection of the WordPress site}
        {--prefix=wp_ : WordPress table prefix}
        {--unit=toman : Currency unit of the WooCommerce prices (toman|rial)}
        {--with-images : Download product images}
        {--dry-run : Show what would be imported without saving}';

    protected $description = 'Import a WooCommerce product catalog into a tenant (idempotent, re-runnable)';

    public function handle(ImportWooCommerceCatalog $import, TenantContext $context): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('tenant'))->first();

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $unit = CurrencyUnit::tryFrom((string) $this->option('unit'));

        if ($unit === null) {
            $this->error('--unit must be toman or rial.');

            return self::FAILURE;
        }

        $reader = new WooCommerceReader(DB::connection((string) $this->option('connection')), (string) $this->option('prefix'));

        $result = $context->runAs($tenant, fn () => $import->handle($reader, $unit, (bool) $this->option('with-images'), (bool) $this->option('dry-run')));

        $this->table(['item', 'count'], collect($result['counts'])->map(fn (int $count, string $item) => [$item, $count])->values()->all());

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $this->info($this->option('dry-run') ? 'Dry run: nothing was saved.' : 'Import finished.');

        return self::SUCCESS;
    }
}
