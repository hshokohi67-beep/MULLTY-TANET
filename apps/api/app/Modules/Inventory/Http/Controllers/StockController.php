<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\ManageStock;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Http\Resources\StockMovementResource;
use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\StockMovement;
use App\Support\Localization\PersianNumber;
use App\Support\Validation\TenantExists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class StockController
{
    public function movements(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'ingredient_id' => ['nullable', 'string', 'max:26'],
            'branch_id' => ['nullable', 'string', 'max:26'],
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
        ]);

        return StockMovementResource::collection(
            StockMovement::query()->with('ingredient')
                ->when($f['ingredient_id'] ?? null, fn ($q, $id) => $q->where('ingredient_id', $id))
                ->when($f['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
                ->when($f['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
                ->latest('created_at')->latest('id')
                ->cursorPaginate(50),
        );
    }

    public function adjust(Request $request, ManageStock $stock): JsonResponse
    {
        $request->merge(['quantity' => PersianNumber::toLatin((string) $request->input('quantity'))]);
        $v = $request->validate([
            'ingredient_id' => ['required', 'string', TenantExists::in('ingredients')],
            'branch_id' => ['required', 'string', TenantExists::in('branches')],
            'type' => ['required', Rule::in(['adjustment', 'waste'])],
            'quantity' => ['required', 'numeric', 'not_in:0', 'between:-10000000,10000000'],
            'unit' => ['nullable', 'string', 'in:g,kg,ml,l,pcs,pack'],
            'note' => [$request->input('type') === 'waste' ? 'nullable' : 'required', 'string', 'max:300'],
        ], [], ['note' => 'دلیل', 'quantity' => 'مقدار']);

        $movement = $stock->adjust(
            Ingredient::query()->findOrFail($v['ingredient_id']),
            $v['branch_id'],
            StockMovementType::from($v['type']),
            (float) $v['quantity'],
            $v['unit'] ?? null,
            $v['note'] ?? null,
            (string) $request->user()?->getAuthIdentifier(),
            $request->header('Idempotency-Key') ? 'adjust:'.$request->header('Idempotency-Key') : null,
        );

        return (new StockMovementResource($movement->load('ingredient')))->response()->setStatusCode(201);
    }

    public function count(Request $request, ManageStock $stock): JsonResponse
    {
        $v = $request->validate([
            'branch_id' => ['required', 'string', TenantExists::in('branches')],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.ingredient_id' => ['required', 'string', 'distinct', TenantExists::in('ingredients')],
            'lines.*.counted' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'lines.*.unit' => ['nullable', 'string', 'in:g,kg,ml,l,pcs,pack'],
        ]);

        $posted = $stock->count($v['branch_id'], array_values($v['lines']), (string) $request->user()?->getAuthIdentifier(),
            $request->header('Idempotency-Key') ? 'count:'.$request->header('Idempotency-Key') : null);

        return response()->json(['data' => ['adjusted' => $posted]]);
    }
}
