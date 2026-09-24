<?php

namespace App\Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class UploadCoverRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // A wide photo for the storefront hero; re-encoded to WebP server-side.
            'cover' => [
                'required',
                File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(8 * 1024)
                    ->dimensions(Rule::dimensions()->minWidth(800)->minHeight(300)->maxWidth(8000)->maxHeight(8000)),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['cover' => 'تصویر کاور'];
    }
}
