<?php

namespace App\Modules\Occasions\Enums;

/** Mourning occasions get respectful reminders, never promotions or festive themes. */
enum OccasionTone: string
{
    case Festive = 'festive';
    case Calm = 'calm';
    case Mourning = 'mourning';
}
