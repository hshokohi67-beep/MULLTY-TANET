<?php

namespace App\Modules\Catalog\Support;

use App\Support\Localization\PersianNumber;
use Closure;

/**
 * Platform slug policy (discovery D6): Persian slugs are kept for SEO ("لاته-وانیلی"),
 * letters and digits of any script only, words joined by "-". Stable IDs remain the source of truth.
 */
final class Slugger
{
    public static function make(string $text): string
    {
        $text = PersianNumber::toLatin(mb_strtolower(trim($text)));
        $text = strtr($text, ['ي' => 'ی', 'ك' => 'ک']);
        $text = preg_replace('/[\x{200C}\s_]+/u', '-', $text) ?? '';
        $text = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $text) ?? '';
        $text = preg_replace('/-+/', '-', $text) ?? '';

        return mb_substr(trim($text, '-'), 0, 100) ?: 'item';
    }

    /**
     * @param  Closure(string): bool  $exists
     */
    public static function unique(string $text, Closure $exists): string
    {
        $base = self::make($text);
        $slug = $base;

        for ($i = 2; $exists($slug); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
