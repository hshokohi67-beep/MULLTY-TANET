<?php

namespace App\Support\Localization;

/**
 * ISO weekday numbers (1 = Monday … 7 = Sunday) with Persian labels and the
 * Iranian display order, where the week starts on Saturday.
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Saturday => 'شنبه',
            self::Sunday => 'یکشنبه',
            self::Monday => 'دوشنبه',
            self::Tuesday => 'سه‌شنبه',
            self::Wednesday => 'چهارشنبه',
            self::Thursday => 'پنجشنبه',
            self::Friday => 'جمعه',
        };
    }

    /**
     * @return list<self>
     */
    public static function iranianOrder(): array
    {
        return [self::Saturday, self::Sunday, self::Monday, self::Tuesday, self::Wednesday, self::Thursday, self::Friday];
    }
}
