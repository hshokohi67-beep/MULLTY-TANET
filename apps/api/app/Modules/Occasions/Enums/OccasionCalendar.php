<?php

namespace App\Modules\Occasions\Enums;

enum OccasionCalendar: string
{
    case Jalali = 'jalali';
    case Hijri = 'hijri';
    case Gregorian = 'gregorian';
}
