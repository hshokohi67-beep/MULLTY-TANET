<?php

namespace App\Modules\Analytics\Support;

use Carbon\CarbonImmutable;

/**
 * Flat tables for CSV/Excel export. Money is in toman (what owners read and type), dates are
 * Jalali text; numbers stay numeric so spreadsheets can sum them.
 */
final class ReportTables
{
    public const REPORTS = ['summary', 'daily', 'products', 'branches'];

    public function __construct(private readonly Reports $reports, private readonly Period $period) {}

    /** @return array{title: string, headers: list<string>, rows: list<list<string|int|float|null>>, widths: list<int>} */
    public function table(string $report, string $compare): array
    {
        return match ($report) {
            'daily' => $this->daily(),
            'products' => $this->products(),
            'branches' => $this->branches(),
            default => $this->summary($compare),
        };
    }

    /** @return array{title: string, headers: list<string>, rows: list<list<string|int|float|null>>, widths: list<int>} */
    private function summary(string $compare): array
    {
        $s = $this->reports->summary($compare);
        $t = $s['totals'];
        $p = $s['previous'];
        $lines = [
            ['فروش (تومان)', 'sales', true], ['تعداد سفارش', 'orders', false], ['میانگین هر سفارش (تومان)', 'average', true],
            ['تخفیف (تومان)', 'discounts', true], ['بازپرداخت (تومان)', 'refunds', true], ['فروش خالص (تومان)', 'net_sales', true],
            ['بهای مواد مصرفی (تومان)', 'cogs', true], ['دستمزد (تومان)', 'labour', true], ['هزینه‌ها (تومان)', 'expenses', true],
            ['ضایعات (تومان)', 'waste', true], ['سود (تومان)', 'profit', true], ['اقلام فروخته‌شده', 'items', false],
            ['سفارش لغوشده', 'cancelled', false],
        ];
        $rows = [];
        foreach ($lines as [$label, $key, $money]) {
            $now = (int) $t[$key];
            $before = $p !== null ? (int) $p[$key] : null;
            $rows[] = [
                $label,
                $money ? self::toman($now) : $now,
                $before === null ? null : ($money ? self::toman($before) : $before),
                $before ? round(($now - $before) / abs($before) * 100, 1) : null,
            ];
        }

        return [
            'title' => 'خلاصه',
            'headers' => ['شاخص', "{$s['period']['from_jalali']} تا {$s['period']['to_jalali']}", $s['compare'] ? "{$s['compare']['from_jalali']} تا {$s['compare']['to_jalali']}" : 'مقایسه', 'تغییر (٪)'],
            'rows' => $rows,
            'widths' => [28, 24, 24, 12],
        ];
    }

    /** @return array{title: string, headers: list<string>, rows: list<list<string|int|float|null>>, widths: list<int>} */
    private function daily(): array
    {
        $tz = $this->period->timezone;
        $rows = [];
        $metrics = Metrics::daily($this->period->fromDate(), $this->period->toDate(), $this->reportsBranch())
            ->groupBy(fn ($m) => $m->business_date->toDateString());
        $weekday = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];

        for ($d = $this->period->from; $d->lte($this->period->to); $d = $d->addDay()) {
            $g = $metrics->get($d->toDateString(), collect());
            $sum = fn (string $f) => (int) $g->sum($f);
            $net = $sum('sales') - $sum('refunds');
            $rows[] = [
                Period::jalali(CarbonImmutable::parse($d->toDateString(), $tz), $tz), $weekday[$d->dayOfWeek],
                $sum('orders'), self::toman($sum('sales')), self::toman($sum('discounts')), self::toman($sum('refunds')),
                $sum('orders') > 0 ? self::toman((int) round($sum('sales') / $sum('orders'))) : 0,
                $sum('items'), self::toman($sum('cogs')), self::toman($sum('labour')), self::toman($sum('expenses')), self::toman($sum('waste')),
                self::toman($net - $sum('cogs') - $sum('labour') - $sum('expenses') - $sum('waste')),
            ];
        }

        return [
            'title' => 'روزانه',
            'headers' => ['تاریخ', 'روز', 'سفارش', 'فروش (تومان)', 'تخفیف', 'بازپرداخت', 'میانگین سفارش', 'اقلام', 'بهای مواد', 'دستمزد', 'هزینه‌ها', 'ضایعات', 'سود'],
            'rows' => $rows,
            'widths' => [12, 10, 8, 16, 12, 12, 14, 8, 14, 14, 14, 12, 16],
        ];
    }

    /** @return array{title: string, headers: list<string>, rows: list<list<string|int|float|null>>, widths: list<int>} */
    private function products(): array
    {
        $data = $this->reports->products();

        return [
            'title' => 'محصولات',
            'headers' => ['محصول', 'دسته', 'تعداد', 'فروش (تومان)', 'سهم از فروش (٪)', 'بهای مواد (تومان)', 'سود ناخالص (تومان)', 'حاشیه (٪)', 'رده'],
            'rows' => array_map(fn (array $p) => [
                $p['name'], $p['category'], $p['quantity'], self::toman($p['revenue']), round($p['share'] * 100, 1),
                $p['cost'] === null ? null : self::toman($p['cost']), $p['margin'] === null ? null : self::toman($p['margin']),
                $p['margin_ratio'] === null ? null : round($p['margin_ratio'] * 100, 1), $p['class'],
            ], $data['products']),
            'widths' => [28, 18, 8, 16, 12, 16, 16, 10, 6],
        ];
    }

    /** @return array{title: string, headers: list<string>, rows: list<list<string|int|float|null>>, widths: list<int>} */
    private function branches(): array
    {
        return [
            'title' => 'شعبه‌ها',
            'headers' => ['شعبه', 'سفارش', 'فروش (تومان)', 'سهم (٪)', 'میانگین سفارش', 'بهای مواد', 'دستمزد', 'هزینه‌ها', 'سود'],
            'rows' => array_map(fn (array $b) => [
                $b['name'], $b['orders'], self::toman($b['sales']), round($b['share'] * 100, 1), self::toman($b['average']),
                self::toman($b['cogs']), self::toman($b['labour']), self::toman($b['expenses']), self::toman($b['profit']),
            ], $this->reports->branches()['branches']),
            'widths' => [22, 8, 16, 8, 14, 14, 14, 14, 16],
        ];
    }

    private function reportsBranch(): ?string
    {
        return $this->reports->branchId();
    }

    /** Rial → toman; whole numbers stay integers. */
    public static function toman(int $rial): int|float
    {
        return $rial % 10 === 0 ? intdiv($rial, 10) : round($rial / 10, 1);
    }
}
