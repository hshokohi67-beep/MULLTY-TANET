<?php

namespace App\Support\Validation;

use App\Support\Localization\PhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class IranianMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PhoneNormalizer::tryNormalize($value) === null) {
            $fail('validation.iranian_mobile')->translate();
        }
    }
}
