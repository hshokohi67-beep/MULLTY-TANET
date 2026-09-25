<?php

namespace App\Modules\Operations\Support;

use App\Modules\Operations\Models\Expense;
use App\Modules\Operations\Models\ExpenseCategory;

/** Expenses between two tenant-local dates, in total and per category (largest first). */
final class ExpenseSummary
{
    /** @return array{total: int, categories: list<array{id: string, name: string, color: int, amount: int}>} */
    public static function between(string $from, string $to, ?string $branchId): array
    {
        $sums = Expense::query()->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->selectRaw('category_id, sum(amount) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $categories = ExpenseCategory::query()->whereIn('id', $sums->keys())->get()->keyBy('id');

        return [
            'total' => (int) $sums->sum(),
            'categories' => $sums->map(fn ($total, $id) => [
                'id' => (string) $id,
                'name' => $categories[$id]->name ?? '',
                'color' => $categories[$id]->color ?? 0,
                'amount' => (int) $total,
            ])->sortByDesc('amount')->values()->all(),
        ];
    }
}
