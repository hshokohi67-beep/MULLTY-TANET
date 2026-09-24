<?php

namespace App\Support\Validation;

use App\Support\Localization\PersianNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Converts Persian/Arabic digits to Latin in the listed fields before validation,
 * so "۱۲۳۴۵" passes a digits rule and is stored as "12345".
 *
 * @mixin FormRequest
 */
trait NormalizesPersianInput
{
    /** @param list<string> $fields */
    protected function latinDigits(array $fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim(PersianNumber::toLatin($value));
            }
        }

        $this->merge($normalized);
    }
}
