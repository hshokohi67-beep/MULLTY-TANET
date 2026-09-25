<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\ManageRecipes;
use App\Modules\Inventory\Http\Requests\RecipeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecipeController
{
    public function show(Request $request, Product $product, ManageRecipes $recipes): JsonResponse
    {
        $branchId = $request->validate(['branch_id' => ['nullable', 'string', 'max:26']])['branch_id'] ?? null;

        return response()->json(['data' => $recipes->show($product, $branchId)]);
    }

    public function update(RecipeRequest $request, Product $product, ManageRecipes $recipes): JsonResponse
    {
        $v = $request->validated();
        $recipes->save($product, array_values($v['variants'] ?? []), array_values($v['modifiers'] ?? []));

        return response()->json(['data' => $recipes->show($product->fresh() ?? $product)]);
    }
}
