<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\BulkUpdatePrices;
use App\Modules\Catalog\Actions\SyncBranchPrices;
use App\Modules\Catalog\Http\Requests\BranchPricesRequest;
use App\Modules\Catalog\Http\Requests\BulkPriceRequest;
use App\Modules\Catalog\Http\Resources\PriceChangeLogResource;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\PriceChangeLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\BulkPriceOperation;
use App\Modules\Core\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PricingController
{
    public function branchPrices(BranchPricesRequest $request, Product $product, SyncBranchPrices $sync): ProductResource
    {
        $branch = Branch::query()->findOrFail($request->validated('branch_id'));
        $sync->handle($product, $branch, $request->amounts(), $request->user()?->getAuthIdentifier());

        return new ProductResource($product->refresh()->load(['categories', 'variants.prices', 'images', 'modifierGroups', 'availability']));
    }

    public function bulk(BulkPriceRequest $request, BulkUpdatePrices $bulk): JsonResponse
    {
        $v = $request->validated();
        $preview = (bool) ($v['preview'] ?? false);

        $result = $bulk->handle(
            $request->target(),
            $v['branch_id'] ?? null,
            BulkPriceOperation::from($v['operation']),
            (int) $v['value'],
            (int) ($v['round_to'] ?? 0),
            $preview,
            $request->user()?->getAuthIdentifier(),
        );

        return response()->json([
            'data' => [
                'preview' => $preview,
                'batch_id' => $result['batch_id'],
                'changed_count' => count($result['rows']),
                'rows' => $result['rows'],
            ],
            'message' => $preview ? null : __('messages.prices_updated', ['count' => count($result['rows'])]),
        ]);
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'variant_id' => ['nullable', 'string', 'max:26'],
            'batch_id' => ['nullable', 'string', 'max:26'],
        ]);

        return PriceChangeLogResource::collection(
            PriceChangeLog::query()
                ->when($filters['variant_id'] ?? null, fn ($q, $id) => $q->where('variant_id', $id))
                ->when($filters['batch_id'] ?? null, fn ($q, $id) => $q->where('batch_id', $id))
                ->latest('created_at')->latest('id')
                ->cursorPaginate(50),
        );
    }
}
