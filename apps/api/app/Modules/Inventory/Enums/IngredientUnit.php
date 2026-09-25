<?php

namespace App\Modules\Inventory\Enums;

/**
 * Base units for stock. Staff may type larger units (kg, L), which convert to the base on input.
 */
enum IngredientUnit: string
{
    case Gram = 'g';
    case Millilitre = 'ml';
    case Piece = 'pcs';

    public function label(): string
    {
        return match ($this) {
            self::Gram => 'گرم',
            self::Millilitre => 'میلی‌لیتر',
            self::Piece => 'عدد',
        };
    }

    /** The larger unit costs and stock are usually read in (کیلو / لیتر / عدد). */
    public function bigLabel(): string
    {
        return match ($this) {
            self::Gram => 'کیلوگرم',
            self::Millilitre => 'لیتر',
            self::Piece => 'عدد',
        };
    }

    /**
     * Entry units accepted for this base unit => factor to the base.
     *
     * @return array<string, int>
     */
    public function entryUnits(): array
    {
        return match ($this) {
            self::Gram => ['g' => 1, 'kg' => 1000],
            self::Millilitre => ['ml' => 1, 'l' => 1000],
            self::Piece => ['pcs' => 1],
        };
    }
}
