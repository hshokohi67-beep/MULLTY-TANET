<?php

namespace App\Support\Sms;

/**
 * Contract every SMS provider adapter implements (Kavenegar, Melipayamak, IPPanel, Trez, …).
 * Business code depends on this interface only, never on a concrete provider.
 */
interface SmsProvider
{
    /** Free-text message (campaigns, notifications). */
    public function send(SmsMessage $message): SmsResult;

    /**
     * Verification code through the provider's template/lookup API.
     * Iranian operators deliver template messages faster and more reliably than free text.
     */
    public function sendVerificationCode(string $phoneE164, string $code): SmsResult;
}
