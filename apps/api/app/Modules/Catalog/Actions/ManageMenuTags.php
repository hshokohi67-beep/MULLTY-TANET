<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\CatalogRuleException;
use App\Modules\Catalog\Models\MenuTag;
use App\Modules\Catalog\Models\Product;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/** The café's own diet/allergy tags: add one, or delete it (and take it off every product). */
final class ManageMenuTags
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function add(string $label): MenuTag
    {
        if (MenuTag::query()->count() >= MenuTag::MAX_PER_TENANT) {
            throw CatalogRuleException::tooManyTags(MenuTag::MAX_PER_TENANT);
        }

        $tag = MenuTag::query()->create(['label' => $label]);
        $this->audit->record('menu_tag.created', $tag, ['label' => $label]);

        return $tag;
    }

    public function delete(MenuTag $tag): void
    {
        DB::transaction(function () use ($tag): void {
            $key = $tag->key();
            // json_contains isn't portable across our databases: the tagged set is small, filter here.
            Product::query()->whereNotNull('dietary_tags')->where('dietary_tags', 'like', '%'.$key.'%')->get()
                ->each(function (Product $product) use ($key): void {
                    $product->dietary_tags = array_values(array_filter($product->dietary_tags ?? [], fn (string $t) => $t !== $key)) ?: null;
                    $product->save();
                });
            $tag->delete();
        });
        $this->audit->record('menu_tag.deleted', null, ['label' => $tag->label]);
    }
}
