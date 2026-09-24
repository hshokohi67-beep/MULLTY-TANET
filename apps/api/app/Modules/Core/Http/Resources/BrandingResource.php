<?php

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Models\TenantBranding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin TenantBranding */
final class BrandingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'logo_url' => $this->logo_path ? Storage::disk(config('filesystems.media_disk'))->url($this->logo_path) : null,
            'cover_url' => $this->cover_path ? Storage::disk(config('filesystems.media_disk'))->url($this->cover_path) : null,
            'primary_color' => $this->primary_color,
            'theme' => $this->theme,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
        ];
    }
}
