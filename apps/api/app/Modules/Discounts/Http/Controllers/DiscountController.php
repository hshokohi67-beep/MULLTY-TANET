<?php

namespace App\Modules\Discounts\Http\Controllers;

use App\Modules\Discounts\Actions\SaveDiscount;
use App\Modules\Discounts\Http\Requests\DiscountRequest;
use App\Modules\Discounts\Http\Resources\DiscountResource;
use App\Modules\Discounts\Models\Discount;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class DiscountController
{
    public function index(): AnonymousResourceCollection
    {
        return DiscountResource::collection(Discount::query()->with('rules')->latest()->get());
    }

    public function store(DiscountRequest $request, SaveDiscount $save): JsonResponse
    {
        return (new DiscountResource($save->handle($request->validated())))->response()->setStatusCode(201);
    }

    public function update(DiscountRequest $request, Discount $discount, SaveDiscount $save): DiscountResource
    {
        return new DiscountResource($save->handle($request->validated(), $discount));
    }

    /** Used discounts are deactivated (orders reference them); unused ones are deleted. */
    public function destroy(Discount $discount, AuditLogger $audit): Response
    {
        if ($discount->usages()->exists()) {
            $discount->update(['is_active' => false]);
            $audit->record('discount.deactivated', $discount, ['name' => $discount->name]);
        } else {
            $discount->delete();
            $audit->record('discount.deleted', $discount, ['name' => $discount->name]);
        }

        return response()->noContent();
    }
}
