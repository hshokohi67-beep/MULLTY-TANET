<?php

namespace App\Modules\Catalog\Support;

use App\Support\Localization\PersianNumber;
use App\Support\Localization\PersianTextNormalizer;

/**
 * Understands a menu spreadsheet: finds the columns by their titles (Persian or English, including
 * a WooCommerce product export) and turns each row into a clean item or a list of problems.
 */
final class CatalogSheet
{
    public const FIELDS = ['name', 'category', 'price', 'description', 'calories', 'type'];

    /** Column titles we recognise, normalised (lower-case, letters and digits only). */
    private const ALIASES = [
        'name' => ['name', 'productname', 'title', 'نام', 'ناممحصول', 'محصول', 'عنوان', 'نامکالا', 'کالا'],
        'category' => ['categories', 'category', 'دسته', 'دستهبندی', 'دستهبندیها', 'گروه', 'دستهها', 'منو'],
        'price' => ['regularprice', 'price', 'قیمت', 'قیمتتومان', 'قیمتبهتومان', 'قیمتریال', 'بها', 'مبلغ'],
        'description' => ['shortdescription', 'description', 'توضیح', 'توضیحات', 'شرح', 'مواد', 'ترکیبات'],
        'calories' => ['calories', 'calorie', 'calory', 'کالری', 'انرژی'],
        'type' => ['type', 'نوع'],
    ];

    public static function normalise(string $text): string
    {
        $text = mb_strtolower(PersianNumber::toLatin(str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ''], $text)));

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $text);
    }

    /**
     * field => column index, guessed from the header row.
     *
     * @param  list<string>  $header
     * @return array<string, int>
     */
    public static function guessColumns(array $header): array
    {
        $map = [];
        foreach ($header as $i => $title) {
            $key = self::normalise($title);
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($key, $aliases, true)) {
                    $map[$field] = $i;
                }
            }
        }

        return $map;
    }

    /** "۱۵۵,۰۰۰ تومان" → 155000; null when there is no number. */
    public static function amount(string $text): ?int
    {
        $digits = preg_replace('/[^0-9]/', '', PersianNumber::toLatin($text));

        return $digits === '' || $digits === null ? null : (int) substr($digits, 0, 12);
    }

    /**
     * The category path of a cell: WooCommerce writes "Drinks > Hot, Specials" (first path wins).
     *
     * @return list<string>
     */
    public static function categoryPath(string $cell): array
    {
        $first = trim(explode(',', str_replace('،', ',', $cell))[0]);
        $parts = array_values(array_filter(array_map(fn (string $p) => mb_substr(trim($p), 0, 80), preg_split('/\s*>\s*/u', $first) ?: []), fn (string $p) => $p !== ''));

        return array_slice($parts, 0, 3);
    }

    /** Name key for matching existing products and categories (spacing, ی/ک and digits don't matter). */
    public static function key(string $name): string
    {
        return PersianTextNormalizer::forSearch($name);
    }
}
