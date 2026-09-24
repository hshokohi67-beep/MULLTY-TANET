<?php

namespace App\Modules\Core\Enums;

enum TenantStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function canOperate(): bool
    {
        return $this === self::Trial || $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'دوره آزمایشی',
            self::Active => 'فعال',
            self::Suspended => 'معلق',
            self::Archived => 'بایگانی‌شده',
        };
    }
}
