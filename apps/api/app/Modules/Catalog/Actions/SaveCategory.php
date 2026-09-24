<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\CatalogRuleException;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\Slugger;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

final class SaveCategory
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, parent_id?: ?string, slug?: ?string, description?: ?string, sort?: int, is_active?: bool, temperature?: ?string}  $data
     */
    public function handle(array $data, ?Category $category = null): Category
    {
        return DB::transaction(function () use ($data, $category): Category {
            $category ??= new Category;
            $isNew = ! $category->exists;
            $parentId = $data['parent_id'] ?? null;

            if ($parentId !== null) {
                $this->guardHierarchy($category, $parentId);
            }

            $slugSource = ($data['slug'] ?? null) ?: ($isNew ? $data['name'] : null);

            $category->fill([
                'name' => $data['name'],
                'parent_id' => $parentId,
                'description' => $data['description'] ?? $category->description,
                'sort' => $data['sort'] ?? $category->sort ?? 0,
                'is_active' => $data['is_active'] ?? $category->is_active ?? true,
                'temperature' => array_key_exists('temperature', $data) ? $data['temperature'] : $category->temperature,
            ]);

            if ($slugSource !== null) {
                $category->slug = Slugger::unique($slugSource, fn (string $s) => Category::query()
                    ->where('slug', $s)
                    ->when($category->exists, fn ($q) => $q->whereKeyNot($category->getKey()))
                    ->exists());
            }

            $changes = $category->getDirty();
            $category->save();

            $this->audit->record($isNew ? 'category.created' : 'category.updated', $category, $changes);

            return $category;
        });
    }

    private function guardHierarchy(Category $category, string $parentId): void
    {
        $parent = Category::query()->findOrFail($parentId);

        if ($category->exists && in_array($parent->id, $category->selfAndDescendantIds(), true)) {
            throw CatalogRuleException::categoryCycle();
        }

        // Depth of the parent + this node + this node's own subtree must stay within the limit.
        $subtreeDepth = $category->exists ? $this->subtreeHeight($category) : 1;

        if ($parent->depth() + $subtreeDepth > Category::MAX_DEPTH) {
            throw CatalogRuleException::categoryTooDeep();
        }
    }

    private function subtreeHeight(Category $category): int
    {
        $height = 1;
        $frontier = [$category->id];

        while (($frontier = Category::query()->whereIn('parent_id', $frontier)->pluck('id')->all()) !== []) {
            $height++;
        }

        return $height;
    }
}
