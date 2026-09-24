<?php

namespace App\Modules\Core\Enums;

enum DomainType: string
{
    case Subdomain = 'subdomain';
    case Custom = 'custom';
}
