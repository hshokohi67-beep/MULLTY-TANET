<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Category */
final class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sort' => $this->sort,
            'is_active' => $this->is_active,
            'temperature' => $this->temperature,
            'image_url' => $this->image_path ? Storage::disk(config('filesystems.media_disk'))->url($this->image_path) : null,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
