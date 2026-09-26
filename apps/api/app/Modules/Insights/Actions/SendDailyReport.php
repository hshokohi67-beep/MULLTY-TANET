<?php

namespace App\Modules\Insights\Actions;

use App\Modules\Core\Support\TenantSettings;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Insights\Support\Overview;
use App\Support\Localization\PersianNumber;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use App\Support\Sms\CafeMessenger;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * End-of-day SMS to the owner(s) of the current tenant (through the café's own SMS line), at the tenant's chosen local hour,
 * at most once per day. Off unless the tenant enables `reports.daily_sms`.
 */
final class SendDailyReport
{
    public function __construct(private readonly CafeMessenger $sms) {}

    /** @return bool whether a report was sent this run */
    public function handle(?CarbonImmutable $now = null): bool
    {
        $tenant = app(TenantContext::class)->require();
        $local = ($now ?? CarbonImmutable::now())->setTimezone($tenant->timezone);

        if (! TenantSettings::get('reports.daily_sms') || (int) $local->format('G') !== (int) TenantSettings::get('reports.daily_sms_hour')) {
            return false;
        }

        // Once per local day, even if the scheduler runs twice.
        if (! Cache::add("daily-report:{$tenant->id}:{$local->toDateString()}", true, 60 * 60 * 36)) {
            return false;
        }

        $kpis = (new Overview($tenant->timezone, null, $local))->kpis('today');
        $current = $kpis['current'];
        $lastWeek = $kpis['compare']['last_week'] ?? null;
        $n = fn (int $v) => PersianNumber::toPersian(number_format($v));
        $money = fn (int $v) => MoneyFormatter::format(Money::rials($v));

        $change = '';
        if ($lastWeek && $lastWeek['sales'] > 0) {
            $pct = (int) round(($current['sales'] - $lastWeek['sales']) / $lastWeek['sales'] * 100);
            $change = "\n".($pct >= 0 ? '▲ ' : '▼ ').PersianNumber::toPersian((string) abs($pct)).'٪ نسبت به هفته‌ی پیش';
        }

        $text = "گزارش امروز {$tenant->name}\n"
            ."فروش: {$money($current['sales'])}\n"
            ."سفارش: {$n($current['orders'])} • میانگین: {$money($current['average'])}\n"
            ."لغو: {$n($kpis['cancelled'])}"
            .$change;

        $phones = TenantUser::query()
            ->whereHas('roles', fn ($q) => $q->where('key', Role::OWNER))
            ->with('user:id,phone_e164')
            ->get()
            ->map(fn (TenantUser $m) => $m->user->phone_e164)
            ->filter()
            ->values()
            ->all();

        if ($phones === []) {
            return false;
        }

        // From the café's own SMS line (the platform line only carries login codes).
        $this->sms->send('daily_report', array_values(array_map('strval', $phones)), $text);

        return true;
    }
}
