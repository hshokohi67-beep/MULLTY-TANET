<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAvailability;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Dashboard view of a product. Amounts are integer rial.
 * Lists load fewer relations; every block below is included only when loaded.
 *
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk(config('filesystems.media_disk'));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'temperature' => $this->temperature,
            'sort' => $this->sort,
            'nutrition' => $this->nutrition,
            'dietary_tags' => $this->dietary_tags ?? [],
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])->values()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'sku' => $v->sku,
                'is_active' => $v->is_active,
                'base_price' => $v->prices->firstWhere('branch_id', null)?->amount,
                'branch_prices' => $v->prices->whereNotNull('branch_id')
                    ->map(fn (ProductPrice $p) => ['branch_id' => $p->branch_id, 'amount' => $p->amount])->values(),
            ])->values()),
            'price_from' => $this->whenLoaded('variants', fn () => $this->variants
                ->where('is_active', true)
                ->map(fn (ProductVariant $v) => $v->prices->firstWhere('branch_id', null)?->amount)
                ->filter(fn ($amount) => $amount !== null)
                ->min()),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn (ProductImage $i) => [
                'id' => $i->id,
                'url' => $disk->url($i->path),
                'alt' => $i->alt,
                'width' => $i->width,
                'height' => $i->height,
            ])->values()),
            'modifier_groups' => $this->whenLoaded('modifierGroups', fn () => $this->modifierGroups->map(fn (ModifierGroup $g) => ['id' => $g->id, 'name' => $g->name])->values()),
            'availability' => $this->whenLoaded('availability', fn () => $this->availability->map(fn (ProductAvailability $a) => [
                'branch_id' => $a->branch_id,
                'status' => $a->effectiveStatus()->value,
                'status_label' => $a->effectiveStatus()->label(),
                'sold_out_until' => $a->sold_out_until?->toIso8601String(),
            ])->values()),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
