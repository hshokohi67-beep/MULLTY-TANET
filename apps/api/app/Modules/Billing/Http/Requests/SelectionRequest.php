<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A plan/cycle/add-on selection (for a quote or a checkout). */
final class SelectionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'string', Rule::exists('plans', 'id')->where('is_public', true)],
            'cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'addons' => ['nullable', 'array', 'max:10'],
            'addons.*.addon_id' => ['required', 'string', 'distinct', Rule::exists('addons', 'id')->where('is_public', true)],
            'addons.*.quantity' => ['required', 'integer', 'between:1,20'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['plan_id' => 'پلن', 'cycle' => 'دوره', 'addons.*.quantity' => 'تعداد افزونه'];
    }

    public function plan(): Plan
    {
        return Plan::query()->findOrFail((string) $this->input('plan_id'));
    }

    /** @return array<string, int> addon id => quantity */
    public function addonQuantities(): array
    {
        $out = [];
        foreach ((array) $this->input('addons', []) as $row) {
            if (is_array($row) && isset($row['addon_id'], $row['quantity'])) {
                $out[(string) $row['addon_id']] = (int) $row['quantity'];
            }
        }

        return $out;
    }
}
