<?php

namespace App\Modules\Catalog\Enums;

enum PriceChangeReason: string
{
    case Manual = 'manual';
    case Bulk = 'bulk';
    case Import = 'import';
}
