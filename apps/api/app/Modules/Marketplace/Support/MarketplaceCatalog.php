<?php

namespace App\Modules\Marketplace\Support;

/** The marketplace's fixed vocabulary: what kind of place it is and what it offers. */
final class MarketplaceCatalog
{
    /** key => Persian label (the web maps keys to icons). */
    public const CATEGORIES = [
        'cafe' => 'کافه',
        'specialty_coffee' => 'قهوه‌ی تخصصی',
        'cafe_restaurant' => 'کافه‌رستوران',
        'restaurant' => 'رستوران',
        'fast_food' => 'فست‌فود',
        'breakfast' => 'صبحانه و برانچ',
        'bakery' => 'نان و شیرینی',
        'dessert' => 'کیک و دسر',
        'ice_cream' => 'بستنی و آبمیوه',
        'tea_house' => 'چای‌خانه',
        'healthy' => 'سالم و رژیمی',
    ];

    public const AMENITIES = [
        'wifi' => 'اینترنت رایگان',
        'outdoor' => 'فضای باز',
        'parking' => 'پارکینگ',
        'family' => 'مناسب خانواده',
        'pet_friendly' => 'پذیرش حیوان خانگی',
        'workspace' => 'مناسب کار و مطالعه',
        'live_music' => 'موسیقی زنده',
        'no_smoking' => 'بدون دخانیات',
        'wheelchair' => 'دسترسی ویلچر',
        'late_night' => 'تا دیروقت باز',
    ];

    /** Menu dietary tags a visitor can filter by (from the café's active products). */
    public const DIETARY = [
        'vegan' => 'گیاهی',
        'vegetarian' => 'گیاه‌خواری',
        'gluten_free' => 'بدون گلوتن',
        'sugar_free' => 'بدون قند',
        'dairy_free' => 'بدون لبنیات',
        'low_calorie' => 'کم‌کالری',
    ];

    public const PRICE_LEVELS = [1 => 'اقتصادی', 2 => 'متوسط', 3 => 'بالا', 4 => 'لوکس'];

    public const MAX_CATEGORIES = 3;

    /**
     * @param  list<string>  $keys
     * @param  array<string, string>  $source
     * @return list<array{key: string, label: string}>
     */
    public static function labelled(array $keys, array $source): array
    {
        return array_values(array_map(fn (string $k) => ['key' => $k, 'label' => $source[$k]], array_filter($keys, fn (string $k) => isset($source[$k]))));
    }
}
