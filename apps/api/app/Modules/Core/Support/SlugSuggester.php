<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\Tenant;

/**
 * A café's web address ({slug}.cafeyar.ir): validity, availability and a suggestion from its
 * Persian name ("کافه نارنج" → "kafe-naranj"), with -2, -3… when that one is taken.
 */
final class SlugSuggester
{
    public const PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    private const LETTERS = [
        'آ' => 'a', 'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's', 'ج' => 'j', 'چ' => 'ch',
        'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh', 'س' => 's', 'ش' => 'sh',
        'ص' => 's', 'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => '', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'gh', 'ک' => 'k', 'ك' => 'k',
        'گ' => 'g', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'و' => 'v', 'ه' => 'h', 'ة' => 'h', 'ی' => 'i', 'ي' => 'i', 'ئ' => 'i',
        'ء' => '', "\u{200C}" => '', '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6',
        '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** Common words, spelled the way people expect to type them (short vowels aren't written in Persian). */
    private const WORDS = [
        'کافه' => 'cafe', 'كافه' => 'cafe', 'کافی' => 'cafe', 'رستوران' => 'restaurant', 'قهوه' => 'coffee', 'کافی‌شاپ' => 'coffeeshop',
        'شیرینی' => 'shirini', 'بستنی' => 'bastani', 'نان' => 'nan', 'نانوایی' => 'nanvaei', 'فست' => 'fast', 'فود' => 'food',
        'پیتزا' => 'pizza', 'برگر' => 'burger', 'کیک' => 'cake', 'چای' => 'chai', 'خانه' => 'khane', 'باغ' => 'bagh', 'آشپزخانه' => 'kitchen',
    ];

    public static function valid(string $slug): bool
    {
        return strlen($slug) >= 3 && strlen($slug) <= 30 && preg_match(self::PATTERN, $slug) === 1
            && ! in_array($slug, (array) config('tenancy.reserved_slugs'), true);
    }

    public static function available(string $slug): bool
    {
        return self::valid($slug) && ! Tenant::query()->where('slug', $slug)->exists();
    }

    /** A free address based on the name (never null: falls back to cafe-…). */
    public static function suggest(string $name): string
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($name))) ?: [];
        $latin = implode(' ', array_map(function (string $word): string {
            if (isset(self::WORDS[$word])) {
                return self::WORDS[$word];
            }
            // A word-final «ه» after a letter is usually the vowel "e" (خانه → khane).
            $word = (string) preg_replace('/(?<=\p{L})ه$/u', 'e', $word);

            return strtr($word, self::LETTERS);
        }, $words));
        $slug = trim((string) preg_replace('/-+/', '-', (string) preg_replace('/[^a-z0-9]+/', '-', $latin)), '-');
        $slug = substr($slug, 0, 26);
        if (strlen($slug) < 3 || ! ctype_alpha($slug[0])) {
            $slug = 'cafe-'.($slug !== '' ? $slug : strtolower(substr(bin2hex(random_bytes(3)), 0, 5)));
        }
        $slug = rtrim($slug, '-');

        for ($i = 1; $i <= 30; $i++) {
            $candidate = $i === 1 ? $slug : $slug.'-'.$i;
            if (self::available($candidate)) {
                return $candidate;
            }
        }

        return 'cafe-'.strtolower(bin2hex(random_bytes(3)));
    }
}
