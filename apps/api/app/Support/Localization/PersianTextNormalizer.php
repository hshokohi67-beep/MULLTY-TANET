<?php

namespace App\Support\Localization;

/**
 * Normalises Persian text so visually identical strings compare equal.
 * Used for search columns and search input; never to rewrite what the user typed for display.
 */
final class PersianTextNormalizer
{
    private const CHAR_MAP = [
        'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی',
        'ك' => 'ک',
        'ة' => 'ه', 'ۀ' => 'ه', 'ە' => 'ه',
        'أ' => 'ا', 'إ' => 'ا', 'ٱ' => 'ا',
        'ؤ' => 'و',
    ];

    public static function forSearch(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = strtr($text, self::CHAR_MAP);
        $text = PersianNumber::toLatin($text);
        // Arabic diacritics (harakat, tanween, shadda, sukun, superscript alef) and tatweel.
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;
        // ZWNJ / ZWJ / bidi marks become plain spaces: "می‌خواهم" matches "می خواهم".
        $text = preg_replace('/[\x{200C}\x{200D}\x{200E}\x{200F}\x{00A0}]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strtolower(trim($text));
    }
}
