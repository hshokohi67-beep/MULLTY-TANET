<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Modules\Operations\Exceptions\OperationsException;
use App\Modules\Operations\Http\Requests\ExpenseRequest;
use App\Modules\Operations\Http\Resources\ExpenseResource;
use App\Modules\Operations\Models\Expense;
use App\Modules\Operations\Models\ExpenseCategory;
use App\Modules\Operations\Support\ExpenseSummary;
use App\Modules\Operations\Support\LocalRange;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class ExpenseController
{
    public function categories(): JsonResponse
    {
        // First visit: start with sensible Persian categories.
        if (! ExpenseCategory::query()->exists()) {
            foreach (ExpenseCategory::DEFAULTS as $i => $name) {
                ExpenseCategory::query()->create(['name' => $name, 'color' => $i % 4]);
            }
        }

        $used = Expense::query()->distinct()->pluck('category_id')->flip();

        return response()->json(['data' => ExpenseCategory::query()->orderByDesc('is_active')->orderBy('created_at')->get()
            ->map(fn (ExpenseCategory $c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color, 'is_active' => $c->is_active, 'in_use' => $used->has($c->id)])->values()]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $category = ExpenseCategory::query()->create($this->categoryData($request, null));

        return response()->json(['data' => ['id' => $category->id, 'name' => $category->name]], 201);
    }

    public function updateCategory(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->update($this->categoryData($request, $expenseCategory));

        return response()->json(['data' => ['id' => $expenseCategory->id, 'name' => $expenseCategory->name]]);
    }

    public function destroyCategory(ExpenseCategory $expenseCategory): Response
    {
        if (Expense::query()->where('category_id', $expenseCategory->id)->exists()) {
            throw OperationsException::categoryInUse();
        }
        $expenseCategory->delete();

        return response()->noContent();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        [, , $from, $to] = LocalRange::from($request, 30);
        $f = $request->validate(['branch_id' => ['nullable', 'string', 'max:26'], 'category_id' => ['nullable', 'string', 'max:26']]);

        return ExpenseResource::collection(Expense::query()->with('category')
            ->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)
            ->when($f['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($f['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->orderByDesc('spent_on')->orderByDesc('created_at')->limit(500)->get());
    }

    public function summary(Request $request): JsonResponse
    {
        [, , $from, $to] = LocalRange::from($request, 30);
        $branchId = $request->validate(['branch_id' => ['nullable', 'string', 'max:26']])['branch_id'] ?? null;

        return response()->json(['data' => ExpenseSummary::between($from, $to, $branchId)]);
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        $expense = Expense::query()->create([...$request->validated(), 'recorded_by' => $request->user()?->getAuthIdentifier()]);

        return (new ExpenseResource($expense->load('category')))->response()->setStatusCode(201);
    }

    public function update(ExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $expense->update($request->validated());

        return new ExpenseResource($expense->load('category'));
    }

    public function destroy(Expense $expense): Response
    {
        $expense->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function categoryData(Request $request, ?ExpenseCategory $category): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('expense_categories', 'name')->where('tenant_id', app(TenantContext::class)->id())->ignore($category)],
            'color' => ['sometimes', 'integer', 'between:0,3'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], ['name' => 'نام']);
    }
}
