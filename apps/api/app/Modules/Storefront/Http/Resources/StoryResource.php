<?php

namespace App\Modules\Storefront\Http\Resources;

use App\Modules\Storefront\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A story as staff see it (with status and counters).
 *
 * @mixin Story
 */
final class StoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk(config('filesystems.media_disk'));

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'image_url' => $disk->url($this->image_path),
            'thumb_url' => $disk->url($this->thumb_path),
            'width' => $this->width,
            'height' => $this->height,
            'caption' => $this->caption,
            'link_type' => $this->link_type,
            'link_target' => $this->link_target,
            'cta_label' => $this->cta_label,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'sort' => $this->sort,
            'is_active' => $this->is_active,
            'status' => $this->status(),
            'views' => $this->views,
            'clicks' => $this->clicks,
        ];
    }
}
