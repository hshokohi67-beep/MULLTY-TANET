<?php

namespace App\Modules\Marketplace\Support;

/**
 * One normal form for Persian search: Arabic ي/ك → ی/ک, Persian/Arabic digits → Latin, ZWNJ and
 * diacritics removed, whitespace collapsed, lower-cased. Used both when indexing and when querying.
 */
final class SearchText
{
    public static function normalize(string $text): string
    {
        $text = strtr($text, [
            'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ۀ' => 'ه',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            "\u{200C}" => ' ', "\u{200D}" => '', 'ـ' => '',
        ]);
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim(mb_strtolower($text));
    }

    /** @return list<string> query terms (at most 6, each ≥ 2 characters) */
    public static function terms(string $query): array
    {
        $terms = array_values(array_filter(explode(' ', self::normalize(mb_substr($query, 0, 80))), fn (string $t) => mb_strlen($t) >= 2));

        return array_slice(array_values(array_unique($terms)), 0, 6);
    }
}
