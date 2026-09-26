<?php

namespace App\Modules\Messaging\Support;

/** Placeholders, the opt-out footer and SMS part counting (Persian: 70 per part, 67 when split). */
final class SmsText
{
    /** Standard Iranian opt-out keyword appended to every marketing message. */
    public const OPT_OUT_FOOTER = "\nلغو۱۱";

    /** @param  array<string, string>  $values  placeholder => value (without braces) */
    public static function render(string $body, array $values): string
    {
        $replace = [];
        foreach ($values as $key => $value) {
            $replace['{'.$key.'}'] = $value;
        }

        // Unknown placeholders are dropped rather than sent as "{something}".
        return trim((string) preg_replace('/\{[a-z_]+\}/', '', strtr($body, $replace)));
    }

    public static function parts(string $text): int
    {
        $length = mb_strlen($text);
        if ($length === 0) {
            return 0;
        }
        $unicode = preg_match('/[^\x00-\x7F]/', $text) === 1;
        [$single, $multi] = $unicode ? [70, 67] : [160, 153];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}
