<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Exceptions\InventoryException;
use App\Modules\Inventory\Models\Ingredient;

/**
 * Converts what staff type (2 kg, 1.5 L, 3 packs) to the ingredient's base unit, and a price per
 * entry unit to the stored rial-per-1000-base-units.
 */
final class Units
{
    /** How many base units one entry unit is: g/kg, ml/l, pcs, or the ingredient's pack. */
    public static function factor(Ingredient $ingredient, ?string $entryUnit): float
    {
        $entry = strtolower((string) ($entryUnit ?: $ingredient->unit->value));

        if ($entry === 'pack') {
            return $ingredient->pack_size !== null && (float) $ingredient->pack_size > 0
                ? (float) $ingredient->pack_size
                : throw InventoryException::unitMismatch('بسته');
        }

        return (float) ($ingredient->unit->entryUnits()[$entry] ?? throw InventoryException::unitMismatch($entry));
    }

    public static function toBase(Ingredient $ingredient, float $quantity, ?string $entryUnit): float
    {
        return round($quantity * self::factor($ingredient, $entryUnit), 3);
    }

    /** A price for one entry unit (e.g. per kg, per pack) as rial per 1000 base units. */
    public static function pricePer1000(Ingredient $ingredient, int $pricePerEntryUnit, ?string $entryUnit): int
    {
        return (int) round($pricePerEntryUnit * 1000 / self::factor($ingredient, $entryUnit));
    }
}
