<?php

namespace App\Modules\Messaging\Actions;

use App\Modules\Core\Models\Tenant;
use App\Modules\Messaging\Models\SmsCampaign;
use App\Modules\Messaging\Models\SmsLog;
use App\Modules\Messaging\Support\Audience;
use App\Modules\Messaging\Support\SmsText;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Sends due campaigns in chunks (every minute). Rules:
 *  - only between 08:00 and 21:00 in the café's time zone (otherwise it waits);
 *  - only opted-in customers, re-checked per chunk (someone who opted out meanwhile is skipped);
 *  - at most DAILY_CAP campaign messages per café per local day (then it continues tomorrow);
 *  - the opt-out footer «لغو۱۱» is always appended; resumes from `cursor` after any interruption.
 */
final class RunSmsCampaigns
{
    public const QUIET_FROM = 21;

    public const QUIET_TO = 8;

    public const CHUNK = 100;

    public const PER_RUN = 500;

    public const DAILY_CAP = 5000;

    public function __construct(private readonly TenantContext $context, private readonly SendCafeSms $send) {}

    /** @return int messages sent this run (all cafés) */
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        // Platform scheduler across cafés: find the due campaigns, then work inside each café.
        $due = $this->context->bypass(fn () => SmsCampaign::query()->whereIn('status', ['scheduled', 'sending'])->where('scheduled_at', '<=', $now)->orderBy('scheduled_at')->get(['id', 'tenant_id']));
        $total = 0;

        foreach ($due as $row) {
            $tenant = Tenant::query()->find($row->tenant_id);
            if ($tenant === null) {
                continue;
            }
            $total += $this->context->runAs($tenant, fn () => $this->run($tenant, $row->id, $now));
        }

        return $total;
    }

    private function run(Tenant $tenant, string $id, CarbonImmutable $now): int
    {
        $local = $now->setTimezone($tenant->timezone);
        if ($local->hour >= self::QUIET_FROM || $local->hour < self::QUIET_TO) {
            return 0;
        }
        $campaign = SmsCampaign::query()->find($id);
        if ($campaign === null || ! in_array($campaign->status, ['scheduled', 'sending'], true)) {
            return 0;
        }
        $sentToday = SmsLog::query()->where('kind', 'campaign')->where('status', 'sent')->where('created_at', '>=', $local->startOfDay()->utc())->count();
        $budget = min(self::PER_RUN, self::DAILY_CAP - $sentToday);
        if ($budget <= 0) {
            return 0;
        }
        if ($campaign->status === 'scheduled') {
            $campaign->update(['status' => 'sending', 'started_at' => $now]);
        }

        $body = $campaign->body.SmsText::OPT_OUT_FOOTER;
        $sent = 0;
        while ($budget > 0) {
            $chunk = Audience::query($campaign->audience)
                ->when($campaign->cursor, fn ($q, $c) => $q->where('id', '>', $c))
                ->orderBy('id')->limit(min(self::CHUNK, $budget))->get(['id', 'phone_e164']);
            if ($chunk->isEmpty()) {
                $campaign->update(['status' => 'done', 'finished_at' => now()]);
                break;
            }
            $result = $this->send->handle('campaign', $chunk->pluck('phone_e164')->map(fn ($p) => (string) $p)->values()->all(), $body, $campaign->id);
            $campaign->update([
                'cursor' => (string) $chunk->last()->id,
                'sent' => $campaign->sent + $result['sent'],
                'failed' => $campaign->failed + $result['failed'] + $result['skipped'],
            ]);
            $sent += $result['sent'];
            $budget -= $chunk->count();
            if ($result['skipped'] > 0) {
                break; // the line was disconnected mid-way: stop here, the café sees it in the log
            }
        }

        return $sent;
    }
}
