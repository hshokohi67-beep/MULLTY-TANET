<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\CatalogRuleException;
use App\Modules\Catalog\Models\Category;
use App\Support\Audit\AuditLogger;

final class DeleteCategory
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Category $category): void
    {
        if ($category->children()->exists() || $category->products()->exists()) {
            throw CatalogRuleException::categoryNotEmpty();
        }

        $category->delete();
        $this->audit->record('category.deleted', $category, ['name' => $category->name]);
    }
}
