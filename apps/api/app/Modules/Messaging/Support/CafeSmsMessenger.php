<?php

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Actions\SendCafeSms;
use App\Support\Sms\CafeMessenger;
use App\Support\Tenancy\TenantContext;

/** CafeMessenger over the café's own panel: templates only when switched on. */
final class CafeSmsMessenger implements CafeMessenger
{
    public function template(string $key, string $phoneE164, array $values): void
    {
        $body = TemplateCatalog::enabledBody($key);
        if ($body === null) {
            return;
        }
        $values['cafe'] ??= app(TenantContext::class)->require()->name;

        app(SendCafeSms::class)->handle($key, [$phoneE164], SmsText::render($body, $values));
    }

    public function send(string $kind, array $recipients, string $text): void
    {
        app(SendCafeSms::class)->handle($kind, $recipients, $text);
    }
}
