<?php

namespace App\Modules\Occasions\Enums;

/** What an occasion is, for filtering and for the tone of what cafés are told. */
enum OccasionKind: string
{
    case National = 'national';
    case ReligiousJoy = 'religious_joy';
    case Mourning = 'mourning';
    case Ramadan = 'ramadan';
    case Global = 'global';
    case DrinkDay = 'drink_day';
    case Season = 'season';

    public function label(): string
    {
        return match ($this) {
            self::National => 'ملی و ایرانی',
            self::ReligiousJoy => 'مذهبی (شاد)',
            self::Mourning => 'سوگواری',
            self::Ramadan => 'ماه رمضان',
            self::Global => 'جهانی',
            self::DrinkDay => 'روزهای جهانی نوشیدنی و خوراکی',
            self::Season => 'فصل‌ها',
        };
    }
}
