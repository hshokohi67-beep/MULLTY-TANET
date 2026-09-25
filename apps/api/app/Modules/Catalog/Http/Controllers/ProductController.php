<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\ManageProductImages;
use App\Modules\Catalog\Actions\SaveProduct;
use App\Modules\Catalog\Actions\SetProductAvailability;
use App\Modules\Catalog\Actions\SyncVariants;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\VariantData;
use App\Modules\Catalog\Enums\AvailabilityStatus;
use App\Modules\Catalog\Http\Requests\AvailabilityRequest;
use App\Modules\Catalog\Http\Requests\ProductImageRequest;
use App\Modules\Catalog\Http\Requests\ProductModifierGroupsRequest;
use App\Modules\Catalog\Http\Requests\ProductRequest;
use App\Modules\Catalog\Http\Requests\QuickAddProductRequest;
use App\Modules\Catalog\Http\Requests\VariantsRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Support\CatalogVersion;
use App\Modules\Core\Models\Branch;
use App\Modules\Identity\Support\PermissionCatalog;
use App\Support\Audit\AuditLogger;
use App\Support\Entitlements\EntitlementGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ProductController
{
    private const DETAIL_RELATIONS = ['categories', 'variants.prices', 'images', 'modifierGroups', 'availability'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'string', 'max:26'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $products = Product::query()
            ->with(['categories', 'variants.prices', 'images', 'availability'])
            ->search($filters['search'] ?? null)
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->whereHas('categories', fn ($c) => $c->whereKey($id)))
            ->when(isset($filters['featured']), fn ($q) => $q->where('is_featured', (bool) $filters['featured']))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy('sort')->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 50));

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request, SaveProduct $save): JsonResponse
    {
        app(EntitlementGate::class)->ensureCanAdd('products', Product::query()->count());
        $product = $save->handle($request->toData(), null, $request->variants(), $request->user()?->getAuthIdentifier());

        return (new ProductResource($product->load(self::DETAIL_RELATIONS)))->response()->setStatusCode(201);
    }

    /** Quick Add: name + price (+ optional category) and the item is on the menu. */
    public function quickAdd(QuickAddProductRequest $request, SaveProduct $save): JsonResponse
    {
        app(EntitlementGate::class)->ensureCanAdd('products', Product::query()->count());
        $v = $request->validated();
        $product = $save->handle(
            new ProductData(name: $v['name'], categoryIds: isset($v['category_id']) ? [$v['category_id']] : []),
            null,
            [new VariantData(id: null, name: null, basePrice: (int) $v['price'])],
            $request->user()?->getAuthIdentifier(),
        );

        return (new ProductResource($product->load(self::DETAIL_RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(self::DETAIL_RELATIONS));
    }

    public function update(ProductRequest $request, Product $product, SaveProduct $save): ProductResource
    {
        // Variants carry prices: changing them through this endpoint also needs the pricing permission.
        if ($request->has('variants')) {
            Gate::authorize(PermissionCatalog::PRICES_MANAGE);
        }

        $save->handle($request->toData(), $product, $request->variants(), $request->user()?->getAuthIdentifier());

        return new ProductResource($product->refresh()->load(self::DETAIL_RELATIONS));
    }

    public function destroy(Product $product, AuditLogger $audit): Response
    {
        // Soft delete: past orders keep pointing at it; it disappears from the menu and dashboard.
        $product->delete();
        $audit->record('product.deleted', $product, ['name' => $product->name]);

        return response()->noContent();
    }

    public function syncVariants(VariantsRequest $request, Product $product, SyncVariants $sync): ProductResource
    {
        $sync->handle($product, $request->variants(), $request->user()?->getAuthIdentifier());

        return new ProductResource($product->refresh()->load(self::DETAIL_RELATIONS));
    }

    public function syncModifierGroups(ProductModifierGroupsRequest $request, Product $product, TenantContext $context, AuditLogger $audit): ProductResource
    {
        $ids = array_values($request->validated('modifier_group_ids'));
        $tenantId = $context->require()->getKey();

        $product->modifierGroups()->sync(collect($ids)->mapWithKeys(fn (string $id, int $i) => [$id => ['tenant_id' => $tenantId, 'sort' => $i]])->all());
        CatalogVersion::bump();
        $audit->record('product.modifier_groups_updated', $product, ['modifier_group_ids' => $ids]);

        return new ProductResource($product->refresh()->load(self::DETAIL_RELATIONS));
    }

    public function setAvailability(AvailabilityRequest $request, Product $product, SetProductAvailability $set): ProductResource
    {
        $v = $request->validated();
        $branch = Branch::query()->findOrFail($v['branch_id']);

        $set->handle($product, $branch, AvailabilityStatus::from($v['status']), isset($v['sold_out_until']) ? Carbon::parse($v['sold_out_until']) : null);

        return new ProductResource($product->refresh()->load(self::DETAIL_RELATIONS));
    }

    public function uploadImage(ProductImageRequest $request, Product $product, ManageProductImages $images): JsonResponse
    {
        $images->upload($product, $request->file('image'), $request->validated('alt'));

        return (new ProductResource($product->refresh()->load(self::DETAIL_RELATIONS)))->response()->setStatusCode(201);
    }

    public function deleteImage(Product $product, string $image, ManageProductImages $images): ProductResource
    {
        $model = ProductImage::query()->where('product_id', $product->getKey())->findOrFail($image);
        $images->delete($product, $model);

        return new ProductResource($product->refresh()->load(self::DETAIL_RELATIONS));
    }
}
