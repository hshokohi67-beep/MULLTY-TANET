<?php

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Models\SmsAccount;
use App\Modules\Messaging\Models\SmsLog;
use App\Modules\Messaging\Support\SmsText;
use App\Support\Sms\SmsMessage;
use Throwable;

/**
 * The only way a café's own messages leave the platform: through the café's connected panel,
 * never the platform line. Without an active account nothing is sent (logged as skipped).
 * Never throws: a failed SMS must not break an order, a gift or a report. Call outside DB
 * transactions (it talks to the provider over HTTP).
 */
final class SendCafeSms
{
    /**
     * @param  list<string>  $recipients  E.164
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function handle(string $kind, array $recipients, string $body, ?string $campaignId = null): array
    {
        $recipients = array_values(array_unique(array_filter($recipients)));
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        if ($recipients === [] || trim($body) === '') {
            return $result;
        }

        $account = SmsAccount::query()->where('is_active', true)->first();
        $parts = SmsText::parts($body);
        $log = fn (string $to, string $status, ?string $error = null, ?string $ref = null) => SmsLog::query()->create([
            'kind' => $kind, 'campaign_id' => $campaignId, 'recipient' => $to, 'body' => mb_substr($body, 0, 700), 'parts' => $parts,
            'status' => $status, 'error' => $error === null ? null : mb_substr($error, 0, 80), 'provider' => $account?->provider, 'provider_ref' => $ref === null ? null : mb_substr($ref, 0, 64),
            'created_at' => now(),
        ]);

        if ($account === null) {
            foreach ($recipients as $to) {
                $log($to, 'skipped', 'not_connected');
            }
            $result['skipped'] = count($recipients);

            return $result;
        }

        try {
            $outcome = $account->driver()->send(new SmsMessage($recipients, $body));
        } catch (Throwable $e) {
            report($e);
            $outcome = null;
        }

        $ok = $outcome !== null && $outcome->successful;
        foreach ($recipients as $to) {
            $log($to, $ok ? 'sent' : 'failed', $ok ? null : ($outcome->error ?? 'exception'), $outcome?->providerReference);
        }
        $result[$ok ? 'sent' : 'failed'] = count($recipients);
        $account->forceFill(['last_error' => $ok ? null : ($outcome->error ?? 'exception')])->save();

        return $result;
    }
}
