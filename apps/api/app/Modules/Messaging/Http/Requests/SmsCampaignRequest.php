<?php

namespace App\Modules\Messaging\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SmsCampaignRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public static function audienceRules(): array
    {
        return [
            'audience' => ['present', 'array'],
            'audience.tier_id' => ['nullable', 'string', 'size:26'],
            'audience.birth_month' => ['nullable', 'integer', 'between:1,12'],
            'audience.inactive_days' => ['nullable', 'integer', 'between:7,730'],
            'audience.has_ordered' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<mixed>  $a
     * @return array{tier_id?: ?string, birth_month?: ?int, inactive_days?: ?int, has_ordered?: bool}
     */
    public static function audienceFrom(array $a): array
    {
        return array_filter([
            'tier_id' => isset($a['tier_id']) ? (string) $a['tier_id'] : null,
            'birth_month' => isset($a['birth_month']) ? (int) $a['birth_month'] : null,
            'inactive_days' => isset($a['inactive_days']) ? (int) $a['inactive_days'] : null,
            'has_ordered' => (bool) ($a['has_ordered'] ?? false),
        ], fn ($v) => $v !== null && $v !== false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:600'],
            ...self::audienceRules(),
        ];
    }

    /** @return array{name: string, body: string, audience: array{tier_id?: ?string, birth_month?: ?int, inactive_days?: ?int, has_ordered?: bool}} */
    public function campaign(): array
    {
        $v = $this->validated();

        return ['name' => (string) $v['name'], 'body' => (string) $v['body'], 'audience' => self::audienceFrom((array) ($v['audience'] ?? []))];
    }
}
