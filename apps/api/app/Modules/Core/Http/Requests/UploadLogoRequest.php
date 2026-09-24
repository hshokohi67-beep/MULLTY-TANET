<?php

namespace App\Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class UploadLogoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Raster formats only: SVG can carry scripts. Content is sniffed, not just the extension.
            'logo' => [
                'required',
                File::image()
                    ->types(['png', 'jpg', 'jpeg', 'webp'])
                    ->max(1024)
                    ->dimensions(Rule::dimensions()->minWidth(64)->minHeight(64)->maxWidth(2048)->maxHeight(2048)),
            ],
        ];
    }
}
