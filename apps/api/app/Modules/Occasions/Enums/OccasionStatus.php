<?php

namespace App\Modules\Occasions\Enums;

/** A café's own answer to one upcoming occasion. */
enum OccasionStatus: string
{
    case Planned = 'planned';
    case Dismissed = 'dismissed';
    case Done = 'done';
}
