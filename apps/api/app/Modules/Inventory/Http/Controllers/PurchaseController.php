<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\ManagePurchases;
use App\Modules\Inventory\Http\Requests\PurchaseOrderRequest;
use App\Modules\Inventory\Http\Resources\PurchaseOrderResource;
use App\Modules\Inventory\Models\PurchaseOrder;
use App\Modules\Inventory\Models\Supplier;
use App\Support\Localization\PersianNumber;
use App\Support\Localization\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class PurchaseController
{
    public function suppliers(): JsonResponse
    {
        $owed = PurchaseOrder::query()->owing()->selectRaw('supplier_id, sum(total - paid_total) as owed')->groupBy('supplier_id')->pluck('owed', 'supplier_id');
        $orders = PurchaseOrder::query()->where('status', '!=', 'cancelled')->selectRaw('supplier_id, count(*) as n')->groupBy('supplier_id')->pluck('n', 'supplier_id');

        return response()->json(['data' => Supplier::query()->orderByDesc('is_active')->orderBy('name')->get()->map(fn (Supplier $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'phone' => $s->phone,
            'notes' => $s->notes,
            'is_active' => $s->is_active,
            'owed' => (int) ($owed[$s->id] ?? 0),
            'orders' => (int) ($orders[$s->id] ?? 0),
        ])->values()]);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $supplier = Supplier::query()->create($this->supplierData($request));

        return response()->json(['data' => ['id' => $supplier->id, 'name' => $supplier->name]], 201);
    }

    public function updateSupplier(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($this->supplierData($request));

        return response()->json(['data' => ['id' => $supplier->id, 'name' => $supplier->name]]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate(['status' => ['nullable', Rule::in(['draft', 'ordered', 'received', 'cancelled', 'open', 'unpaid'])], 'supplier_id' => ['nullable', 'string', 'max:26']]);

        return PurchaseOrderResource::collection(PurchaseOrder::query()->with(['supplier', 'branch'])
            ->when(($f['status'] ?? null) === 'open', fn ($q) => $q->whereIn('status', ['draft', 'ordered']))
            ->when(($f['status'] ?? null) === 'unpaid', fn ($q) => $q->owing()->whereColumn('paid_total', '<', 'total'))
            ->when(in_array($f['status'] ?? null, ['draft', 'ordered', 'received', 'cancelled'], true), fn ($q) => $q->where('status', $f['status']))
            ->when($f['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->latest('created_at')->latest('number')->cursorPaginate(30));
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder->load(['supplier', 'branch', 'items.ingredient', 'payments']));
    }

    public function store(PurchaseOrderRequest $request, ManagePurchases $purchases): JsonResponse
    {
        $po = $purchases->save($request->validated(), null, (string) $request->user()?->getAuthIdentifier());

        return (new PurchaseOrderResource($po->load(['supplier', 'branch', 'payments'])))->response()->setStatusCode(201);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, ManagePurchases $purchases): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchases->save($request->validated(), $purchaseOrder, (string) $request->user()?->getAuthIdentifier())->load(['supplier', 'branch', 'payments']));
    }

    public function markOrdered(PurchaseOrder $purchaseOrder, ManagePurchases $purchases): PurchaseOrderResource
    {
        return $this->show($purchases->markOrdered($purchaseOrder));
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, ManagePurchases $purchases): PurchaseOrderResource
    {
        $v = $request->validate([
            'lines' => ['nullable', 'array', 'max:100'],
            'lines.*.item_id' => ['required', 'string', 'max:26'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'lines.*.unit' => ['nullable', 'string', 'in:g,kg,ml,l,pcs,pack'],
            'lines.*.unit_price' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
        ]);
        $key = (string) ($request->header('Idempotency-Key') ?: str()->ulid());

        return $this->show($purchases->receive($purchaseOrder, isset($v['lines']) ? array_values($v['lines']) : null, (string) $request->user()?->getAuthIdentifier(), $key));
    }

    public function cancel(PurchaseOrder $purchaseOrder, ManagePurchases $purchases): PurchaseOrderResource
    {
        return $this->show($purchases->cancel($purchaseOrder));
    }

    public function pay(Request $request, PurchaseOrder $purchaseOrder, ManagePurchases $purchases): PurchaseOrderResource
    {
        $request->merge(['amount' => PersianNumber::toLatin((string) $request->input('amount'))]);
        $v = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'note' => ['nullable', 'string', 'max:300'],
        ], [], ['amount' => 'مبلغ']);
        $purchases->pay($purchaseOrder, (int) $v['amount'], $v['method'], $v['note'] ?? null, (string) $request->user()?->getAuthIdentifier());

        return $this->show($purchaseOrder->refresh());
    }

    /** @return array<string, mixed> */
    private function supplierData(Request $request): array
    {
        $request->merge(['phone' => $request->filled('phone') ? PersianNumber::toLatin((string) $request->input('phone')) : null]);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], ['name' => 'نام']);
        if (! empty($v['phone'])) {
            $v['phone'] = PhoneNormalizer::tryNormalize($v['phone']) ?? $v['phone'];
        }

        return $v;
    }
}
