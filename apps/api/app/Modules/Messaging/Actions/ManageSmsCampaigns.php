<?php

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Exceptions\MessagingException;
use App\Modules\Messaging\Models\SmsAccount;
use App\Modules\Messaging\Models\SmsCampaign;
use App\Modules\Messaging\Support\Audience;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;

/** Creating, editing, scheduling and cancelling marketing campaigns (sending is RunSmsCampaigns). */
final class ManageSmsCampaigns
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, body: string, audience: array{tier_id?: ?string, birth_month?: ?int, inactive_days?: ?int, has_ordered?: bool}, send_at?: ?string}  $v
     */
    public function save(?SmsCampaign $campaign, array $v, ?string $userId): SmsCampaign
    {
        if ($campaign !== null && ! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw MessagingException::campaignLocked();
        }
        $campaign ??= new SmsCampaign(['status' => 'draft', 'created_by' => $userId]);
        $campaign->fill(['name' => $v['name'], 'body' => trim($v['body']), 'audience' => $v['audience']]);
        $campaign->save();

        return $campaign;
    }

    /** Queue it: now (next run) or at `send_at`. Needs a connected line and a non-empty audience. */
    public function schedule(SmsCampaign $campaign, ?string $sendAt): SmsCampaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw MessagingException::campaignLocked();
        }
        if (! SmsAccount::query()->where('is_active', true)->exists()) {
            throw MessagingException::notConnected();
        }
        $count = Audience::count($campaign->audience);
        if ($count === 0) {
            throw MessagingException::emptyAudience();
        }
        $at = $sendAt ? CarbonImmutable::parse($sendAt) : CarbonImmutable::now();
        if ($at->lt(CarbonImmutable::now()->subMinute())) {
            throw MessagingException::scheduleInPast();
        }

        $campaign->update(['status' => 'scheduled', 'scheduled_at' => $at, 'recipients' => $count]);
        $this->audit->record('sms.campaign_scheduled', $campaign, ['recipients' => $count, 'at' => $at->toIso8601String()]);

        return $campaign;
    }

    public function cancel(SmsCampaign $campaign): SmsCampaign
    {
        if (in_array($campaign->status, ['done', 'cancelled'], true)) {
            throw MessagingException::campaignLocked();
        }
        $campaign->update(['status' => 'cancelled', 'finished_at' => now()]);
        $this->audit->record('sms.campaign_cancelled', $campaign, ['sent' => $campaign->sent]);

        return $campaign;
    }
}
