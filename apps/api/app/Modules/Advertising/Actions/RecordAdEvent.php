<?php

namespace App\Modules\Advertising\Actions;

use App\Modules\Advertising\Models\AdDailyStat;
use App\Modules\Advertising\Models\AdSlot;
use App\Modules\Advertising\Support\AdTokens;
use App\Modules\Core\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;

/**
 * Counts an impression or a click of a served ad. Only signed, unexpired tokens of existing slots
 * count; each visitor (hashed IP + user agent, kept only as a cache key) counts once per hour for
 * impressions and once per day for clicks. Counters live on `ad_daily_stats` (café's local day).
 */
final class RecordAdEvent
{
    public const TYPES = ['impression', 'click'];

    public function __construct(private readonly TenantContext $context) {}

    public function handle(string $token, string $type, string $ip, string $agent): bool
    {
        $data = AdTokens::read($token);
        $slot = $data === null ? null : AdSlot::query()->where('ref', $data['ref'])->where('placement', $data['placement'])->first();
        if ($slot === null || $slot->starts_at->isFuture() || $slot->ends_at->lt(now()->subHours(3))) {
            return false;
        }

        $impression = $type === 'impression';
        $window = now()->format($impression ? 'YmdH' : 'Ymd');
        $key = 'ad-event:'.hash('sha256', implode('|', [$ip, mb_substr($agent, 0, 200), $slot->ref, $type, $window]));
        if (! Cache::add($key, 1, $impression ? 3600 : 86400)) {
            return false;
        }

        $tenant = Tenant::query()->find($slot->tenant_id);
        if ($tenant === null) {
            return false;
        }
        $this->context->runAs($tenant, function () use ($slot, $tenant, $impression): void {
            $day = now($tenant->timezone)->toDateString();
            $stat = AdDailyStat::query()->where('campaign_id', $slot->campaign_id)->whereDate('day', $day)->first();
            if ($stat === null) {
                try {
                    $stat = AdDailyStat::query()->create(['campaign_id' => $slot->campaign_id, 'day' => $day]);
                } catch (UniqueConstraintViolationException) {
                    $stat = AdDailyStat::query()->where('campaign_id', $slot->campaign_id)->whereDate('day', $day)->firstOrFail();
                }
            }
            $stat->increment($impression ? 'impressions' : 'clicks');
        });

        return true;
    }
}
