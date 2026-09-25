<?php

namespace App\Modules\Operations\Http\Resources;

use App\Modules\Operations\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expense */
final class ExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name, 'color' => $this->category->color]),
            'amount' => $this->amount,
            'spent_on' => $this->spent_on->toDateString(),
            'method' => $this->method,
            'method_label' => Expense::METHODS[$this->method] ?? $this->method,
            'payee' => $this->payee,
            'note' => $this->note,
        ];
    }
}
