<?php

namespace App\Modules\Advertising\Support;

use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdDailyStat;
use Carbon\CarbonImmutable;

/** Read side of the ad counters for the current café. */
final class AdStats
{
    /**
     * Lifetime impressions and clicks per campaign.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, array{impressions: int, clicks: int}>
     */
    public static function totals(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }
        $out = [];
        $rows = AdDailyStat::query()->whereIn('campaign_id', $campaignIds)
            ->selectRaw('campaign_id, SUM(impressions) as i, SUM(clicks) as c')->groupBy('campaign_id')->get();
        foreach ($rows as $row) {
            $out[(string) $row->getAttribute('campaign_id')] = ['impressions' => (int) $row->getAttribute('i'), 'clicks' => (int) $row->getAttribute('c')];
        }

        return $out;
    }

    /**
     * One point per local day, oldest first (days without data are zero).
     *
     * @return list<array{date: string, impressions: int, clicks: int}>
     */
    public static function series(string $timezone, int $days = 30, ?string $campaignId = null): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $from = $today->subDays($days - 1);
        $byDay = [];
        $rows = AdDailyStat::query()->where('day', '>=', $from->format('Y-m-d'))
            ->when($campaignId, fn ($q, $id) => $q->where('campaign_id', $id))
            ->get(['day', 'impressions', 'clicks']);
        foreach ($rows as $row) {
            $key = $row->day->toDateString();
            $byDay[$key] ??= ['impressions' => 0, 'clicks' => 0];
            $byDay[$key]['impressions'] += $row->impressions;
            $byDay[$key]['clicks'] += $row->clicks;
        }

        $out = [];
        for ($d = $from; $d->lte($today); $d = $d->addDay()) {
            $key = $d->format('Y-m-d');
            $out[] = ['date' => $key, 'impressions' => $byDay[$key]['impressions'] ?? 0, 'clicks' => $byDay[$key]['clicks'] ?? 0];
        }

        return $out;
    }

    /**
     * @param  list<array{date: string, impressions: int, clicks: int}>  $series
     * @return array{impressions: int, clicks: int, ctr: ?float, spend: int, live: int, awaiting: int}
     */
    public static function summary(array $series): array
    {
        $impressions = array_sum(array_column($series, 'impressions'));
        $clicks = array_sum(array_column($series, 'clicks'));
        $paid = AdCampaign::query()->whereNotNull('paid_at')->where('paid_at', '>=', now()->subDays(count($series)))->sum('amount');

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round($clicks / $impressions * 100, 1) : null,
            'spend' => (int) $paid,
            'live' => AdCampaign::query()->where('status', AdCampaign::PAID)->where('starts_at', '<=', now())->where('ends_at', '>', now())->count(),
            'awaiting' => AdCampaign::query()->whereIn('status', [AdCampaign::PENDING, AdCampaign::APPROVED])->count(),
        ];
    }
}
