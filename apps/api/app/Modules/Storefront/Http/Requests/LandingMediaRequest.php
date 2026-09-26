<?php

namespace App\Modules\Storefront\Http\Requests;

use App\Modules\Storefront\Models\StorefrontMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/** Upload one landing photo (raster, re-encoded) or the hero video (MP4, at most 8 MB). */
final class LandingMediaRequest extends FormRequest
{
    public const VIDEO_MAX_KB = 8 * 1024;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $video = $this->input('kind') === 'hero_video';

        return [
            'kind' => ['required', Rule::in(StorefrontMedia::KINDS)],
            'file' => $video
                ? ['required', 'file', 'mimetypes:video/mp4,application/mp4,video/quicktime', 'max:'.self::VIDEO_MAX_KB]
                // Raster only (SVG can carry scripts); the file is re-encoded to WebP anyway.
                : ['required', File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(10 * 1024)
                    ->dimensions(Rule::dimensions()->minWidth(600)->minHeight(400)->maxWidth(10000)->maxHeight(10000))],
            'caption' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return ['file' => $this->input('kind') === 'hero_video' ? 'ویدیو' : 'عکس', 'caption' => 'توضیح عکس'];
    }
}
