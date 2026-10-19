<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\SaveMenuCollection;
use App\Modules\Catalog\Http\Requests\MenuCollectionRequest;
use App\Modules\Catalog\Models\MenuCollection;
use App\Modules\Catalog\Models\Product;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** «مجموعه‌ی منو»: seasonal / occasion highlights on the menu. */
final class MenuCollectionController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => MenuCollection::query()->with('products:id,name')->latest()->get()->map(fn (MenuCollection $c) => $this->present($c))->all()]);
    }

    public function store(MenuCollectionRequest $request, SaveMenuCollection $save): JsonResponse
    {
        return response()->json(['data' => $this->present($save->handle($request->validated()))], 201);
    }

    public function update(MenuCollectionRequest $request, MenuCollection $menuCollection, SaveMenuCollection $save): JsonResponse
    {
        return response()->json(['data' => $this->present($save->handle($request->validated(), $menuCollection))]);
    }

    public function destroy(MenuCollection $menuCollection, AuditLogger $audit): Response
    {
        $menuCollection->delete();
        $audit->record('menu_collection.deleted', null, ['title' => $menuCollection->title]);

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function present(MenuCollection $c): array
    {
        return [
            ...$c->only(['id', 'title', 'subtitle', 'theme', 'occasion_key', 'is_active']),
            'starts_at' => $c->starts_at?->toIso8601String(),
            'ends_at' => $c->ends_at?->toIso8601String(),
            'products' => $c->products->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
        ];
    }
}
