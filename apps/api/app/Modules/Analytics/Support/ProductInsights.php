<?php

namespace App\Modules\Analytics\Support;

use App\Modules\Analytics\Models\ProductMetric;
use App\Modules\Analytics\Models\ProductPairMetric;
use App\Modules\Catalog\Contracts\MenuInsights;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Menu insights from the aggregates only (never raw orders): the café's best sellers of the last
 * 30 days and what is bought together over the last 60. Cached for an hour per tenant; the rollup
 * refreshes the aggregates behind it.
 */
final class ProductInsights implements MenuInsights
{
    /** A best seller must have sold at least this many in the window, so a quiet week shows nothing. */
    public const MIN_SOLD = 5;

    /** Two products must have shared at least this many orders to be suggested together. */
    public const MIN_TOGETHER = 2;

    public const POPULAR = 5;

    public function popular(): array
    {
        return Cache::remember($this->key('popular'), now()->addHour(), function (): array {
            $ids = ProductMetric::query()
                ->where('business_date', '>=', $this->since(30))
                ->whereNotNull('product_id')
                ->selectRaw('product_id, SUM(quantity) as sold')
                ->groupBy('product_id')
                ->havingRaw('SUM(quantity) >= ?', [self::MIN_SOLD])
                ->orderByDesc('sold')
                ->limit(self::POPULAR)
                ->pluck('product_id')
                ->all();

            return array_values(array_map('strval', $ids));
        });
    }

    public function pairs(): array
    {
        return Cache::remember($this->key('pairs'), now()->addHour(), function (): array {
            $rows = ProductPairMetric::query()
                ->where('business_date', '>=', $this->since(60))
                ->selectRaw('product_a, product_b, SUM(orders) as together')
                ->groupBy('product_a', 'product_b')
                ->havingRaw('SUM(orders) >= ?', [self::MIN_TOGETHER])
                ->orderByDesc('together')
                ->limit(2000)
                ->get();

            $map = [];
            foreach ($rows as $row) {
                $a = (string) $row->getAttribute('product_a');
                $b = (string) $row->getAttribute('product_b');
                $map[$a][] = $b; // rows arrive strongest first, so each list stays ranked
                $map[$b][] = $a;
            }

            return array_map(fn (array $with) => array_slice($with, 0, 5), $map);
        });
    }

    private function since(int $days): string
    {
        $tenant = app(TenantContext::class)->require();

        return CarbonImmutable::now($tenant->timezone)->subDays($days)->toDateString();
    }

    private function key(string $what): string
    {
        return sprintf('menu-insights:%s:%s:%s', $what, app(TenantContext::class)->require()->id, now()->format('YmdH'));
    }
}
