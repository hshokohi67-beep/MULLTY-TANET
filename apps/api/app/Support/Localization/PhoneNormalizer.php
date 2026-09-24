<?php

namespace App\Support\Localization;

/**
 * Normalises Iranian mobile numbers to one canonical form: +989XXXXXXXXX (E.164).
 *
 * Accepted inputs include 09121234567, ۰۹۱۲۱۲۳۴۵۶۷, +989121234567, 00989121234567,
 * 989121234567, 9121234567, with any spaces, dashes, dots or parentheses.
 * The canonical form is what makes "one phone = one customer per tenant" enforceable.
 */
final class PhoneNormalizer
{
    public static function normalize(string $input): string
    {
        return self::tryNormalize($input) ?? throw new InvalidPhoneNumberException($input);
    }

    public static function tryNormalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = PersianNumber::toLatin(trim($input));
        $hasPlus = str_starts_with($digits, '+');
        $digits = preg_replace('/[\s\-\.\(\)\x{200C}\x{200F}\x{200E}+]/u', '', $digits) ?? '';

        if ($digits === '' || ! ctype_digit($digits)) {
            return null;
        }

        $national = match (true) {
            str_starts_with($digits, '0098') => substr($digits, 4),
            $hasPlus && str_starts_with($digits, '98') => substr($digits, 2),
            strlen($digits) === 12 && str_starts_with($digits, '98') => substr($digits, 2),
            strlen($digits) === 11 && str_starts_with($digits, '0') => substr($digits, 1),
            default => $digits,
        };

        if (! preg_match('/^9\d{9}$/', $national)) {
            return null;
        }

        return '+98'.$national;
    }

    /**
     * +989121234567 → 09121234567 (the local form Iranians expect to see and type).
     */
    public static function toLocal(string $e164): string
    {
        return '0'.substr($e164, 3);
    }
}
