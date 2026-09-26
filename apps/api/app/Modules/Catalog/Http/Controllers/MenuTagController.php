<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\ManageMenuTags;
use App\Modules\Catalog\Models\MenuTag;
use App\Modules\Catalog\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Diet/allergy tags for the product editor: the platform's list plus the café's own. */
final class MenuTagController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'platform' => collect(Product::DIETARY_TAGS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'custom' => MenuTag::query()->orderBy('label')->get()->map(fn (MenuTag $t) => ['id' => $t->id, 'key' => $t->key(), 'label' => $t->label])->all(),
        ]]);
    }

    public function store(Request $request, TenantContext $context, ManageMenuTags $tags): JsonResponse
    {
        $label = trim(preg_replace('/\s+/u', ' ', (string) $request->input('label')) ?? '');
        $request->merge(['label' => $label]);
        $request->validate([
            'label' => [
                'required', 'string', 'min:2', 'max:40', Rule::notIn(array_values(Product::DIETARY_TAGS)),
                Rule::unique('menu_tags', 'label')->where('tenant_id', $context->id()),
            ],
        ], ['label.not_in' => 'این برچسب در فهرست آماده هست؛ همان را تیک بزنید.'], ['label' => 'برچسب']);

        $tag = $tags->add($label);

        return response()->json(['data' => ['id' => $tag->id, 'key' => $tag->key(), 'label' => $tag->label]], 201);
    }

    public function destroy(MenuTag $menuTag, ManageMenuTags $tags): Response
    {
        $tags->delete($menuTag);

        return response()->noContent();
    }
}
