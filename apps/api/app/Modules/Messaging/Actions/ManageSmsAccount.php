<?php

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Exceptions\MessagingException;
use App\Modules\Messaging\Models\SmsAccount;
use App\Modules\Messaging\Models\SmsTemplate;
use App\Modules\Messaging\Support\TemplateCatalog;
use App\Support\Audit\AuditLogger;
use App\Support\Sms\SmsDrivers;

/**
 * Connecting the café's own SMS panel. Secret fields left blank keep their stored value, so the
 * owner never has to re-enter a key just to change the sender line.
 */
final class ManageSmsAccount
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, string>  $fields */
    public function save(string $provider, array $fields, bool $active): SmsAccount
    {
        $spec = SmsDrivers::DRIVERS[$provider] ?? throw MessagingException::unknownProvider();
        $account = SmsAccount::query()->firstOrNew();
        $old = $account->exists && $account->provider === $provider ? $account->credentials : [];

        $credentials = [];
        foreach ($spec['fields'] as $key => $field) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value === '' && $field['secret']) {
                $value = (string) ($old[$key] ?? '');
            }
            // Kavenegar can send from its default line; every other panel needs the sender number.
            if ($value === '' && ! ($key === 'sender' && $provider === 'kavenegar')) {
                throw MessagingException::missingField($field['label']);
            }
            $credentials[$key] = $value;
        }

        $changed = ! $account->exists || $account->provider !== $provider || $account->credentials !== $credentials;
        $account->fill(['provider' => $provider, 'credentials' => $credentials, 'is_active' => $active]);
        if ($changed) {
            $account->fill(['verified_at' => null, 'last_error' => null]);
        }
        $account->save();
        $this->audit->record('sms.account_saved', $account, ['provider' => $provider, 'active' => $active]);

        return $account;
    }

    /** Sends a real test message; a success marks the account verified. */
    public function test(string $phoneE164): bool
    {
        $account = SmsAccount::query()->where('is_active', true)->first() ?? throw MessagingException::notConnected();
        $result = app(SendCafeSms::class)->handle('test', [$phoneE164], 'پیام آزمایشی کافه‌یار: پنل پیامک شما درست وصل شده است.');
        $ok = $result['sent'] > 0;
        if ($ok) {
            $account->forceFill(['verified_at' => now()])->save();
        }

        return $ok;
    }

    /** @param  array<string, array{enabled: bool, body: string}>  $templates */
    public function saveTemplates(array $templates): void
    {
        foreach ($templates as $key => $t) {
            if (! isset(TemplateCatalog::TEMPLATES[$key])) {
                continue;
            }
            SmsTemplate::query()->updateOrCreate(['key' => $key], ['is_enabled' => $t['enabled'], 'body' => trim($t['body']) ?: TemplateCatalog::TEMPLATES[$key]['body']]);
        }
        $this->audit->record('sms.templates_saved', null, ['keys' => array_keys($templates)]);
    }
}
