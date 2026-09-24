<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Modifier;
use App\Modules\Catalog\Models\ModifierGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ModifierGroup */
final class ModifierGroupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'min_select' => $this->min_select,
            'max_select' => $this->max_select,
            'is_required' => $this->isRequired(),
            'sort' => $this->sort,
            'products_count' => $this->whenCounted('products'),
            'modifiers' => $this->whenLoaded('modifiers', fn () => $this->modifiers->map(fn (Modifier $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'price_delta' => $m->price_delta,
                'is_default' => $m->is_default,
                'is_active' => $m->is_active,
            ])->values()),
        ];
    }
}
