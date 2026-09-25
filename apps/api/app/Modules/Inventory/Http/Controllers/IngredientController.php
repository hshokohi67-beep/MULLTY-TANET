<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\ManageStock;
use App\Modules\Inventory\Http\Requests\IngredientRequest;
use App\Modules\Inventory\Http\Resources\IngredientResource;
use App\Modules\Inventory\Models\Ingredient;
use App\Support\Localization\PersianTextNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class IngredientController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:60'], 'low' => ['nullable', 'boolean'], 'active' => ['nullable', 'boolean']]);
        $name = PersianTextNormalizer::forSearch($f['q'] ?? null);

        $items = Ingredient::query()->with('stocks')
            ->when($name !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($name, '%_\\').'%'))
            ->when(isset($f['active']), fn ($q) => $q->where('is_active', (bool) $f['active']))
            ->orderByDesc('is_active')->orderBy('name')->get();

        if (! empty($f['low'])) {
            $items = $items->filter(fn (Ingredient $i) => (float) $i->low_stock_threshold > 0 && $i->stocks->contains(fn ($s) => (float) $s->quantity <= (float) $i->low_stock_threshold))->values();
        }

        return IngredientResource::collection($items);
    }

    public function store(IngredientRequest $request, ManageStock $stock): JsonResponse
    {
        return (new IngredientResource($stock->saveIngredient($request->validated())->load('stocks')))->response()->setStatusCode(201);
    }

    public function update(IngredientRequest $request, Ingredient $ingredient, ManageStock $stock): IngredientResource
    {
        return new IngredientResource($stock->saveIngredient($request->validated(), $ingredient)->load('stocks'));
    }

    public function destroy(Ingredient $ingredient, ManageStock $stock): Response
    {
        $stock->deleteIngredient($ingredient);

        return response()->noContent();
    }
}
