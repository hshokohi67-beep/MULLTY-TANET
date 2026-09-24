<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class ProductImageRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Raster only (SVG can carry scripts); the type is sniffed from the content.
            'image' => ['required', File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(2048)
                ->dimensions(Rule::dimensions()->minWidth(200)->minHeight(200)->maxWidth(4000)->maxHeight(4000))],
            'alt' => ['nullable', 'string', 'max:160'],
        ];
    }
}
