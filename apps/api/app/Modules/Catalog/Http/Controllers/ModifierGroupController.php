<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\SaveModifierGroup;
use App\Modules\Catalog\Http\Requests\ModifierGroupRequest;
use App\Modules\Catalog\Http\Resources\ModifierGroupResource;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class ModifierGroupController
{
    public function index(): AnonymousResourceCollection
    {
        return ModifierGroupResource::collection(ModifierGroup::query()->with('modifiers')->withCount('products')->orderBy('sort')->orderBy('name')->get());
    }

    public function store(ModifierGroupRequest $request, SaveModifierGroup $save): JsonResponse
    {
        $v = $request->validated();

        return (new ModifierGroupResource($save->handle($v, $v['modifiers'])))->response()->setStatusCode(201);
    }

    public function update(ModifierGroupRequest $request, ModifierGroup $modifierGroup, SaveModifierGroup $save): ModifierGroupResource
    {
        $v = $request->validated();

        return new ModifierGroupResource($save->handle($v, $v['modifiers'], $modifierGroup));
    }

    public function destroy(ModifierGroup $modifierGroup, AuditLogger $audit): Response
    {
        $modifierGroup->delete(); // detaches from products via FK cascade
        $audit->record('modifier_group.deleted', $modifierGroup, ['name' => $modifierGroup->name]);

        return response()->noContent();
    }
}
