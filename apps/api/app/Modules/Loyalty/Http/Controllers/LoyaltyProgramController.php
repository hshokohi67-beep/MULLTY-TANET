<?php

namespace App\Modules\Loyalty\Http\Controllers;

use App\Modules\Core\Actions\UpdateTenantSettings;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Core\Support\TenantSettingsRegistry;
use App\Modules\Loyalty\Exceptions\LoyaltyException;
use App\Modules\Loyalty\Models\CashbackRule;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTier;
use App\Modules\Loyalty\Support\ClubSummary;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\TenantExists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Club configuration: program settings, tiers and cashback rules (loyalty.manage). */
final class LoyaltyProgramController
{
    /** The settings this screen owns; they live in the tenant settings registry. */
    public const SETTING_KEYS = [
        'loyalty.enabled', 'loyalty.points_per_100k', 'loyalty.point_value', 'loyalty.min_redeem_points',
        'loyalty.birthday_wallet_gift', 'loyalty.birthday_points', 'loyalty.referral_referrer_reward',
        'loyalty.referral_referee_reward', 'wallet.payments_enabled',
    ];

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->present()]);
    }

    public function update(Request $request, UpdateTenantSettings $update): JsonResponse
    {
        $definitions = TenantSettingsRegistry::definitions();
        $rules = [];
        foreach (self::SETTING_KEYS as $key) {
            $rules[str_replace('.', '\.', $key)] = ['sometimes', ...$definitions[$key]['rules']];
        }

        $input = $request->validate($rules);
        $values = [];
        foreach (self::SETTING_KEYS as $key) {
            if (array_key_exists($key, $input)) {
                $values[$key] = $definitions[$key]['type'] === 'bool' ? (bool) $input[$key] : (int) $input[$key];
            }
        }

        if ($values !== []) {
            $update->handle($values);
        }

        return response()->json(['data' => $this->present(), 'message' => __('messages.saved')]);
    }

    public function storeTier(Request $request, AuditLogger $audit): JsonResponse
    {
        $tier = LoyaltyTier::query()->create($this->tierData($request));
        $audit->record('loyalty.tier_created', $tier, $tier->only(['name', 'min_spend', 'points_multiplier']));

        return response()->json(['data' => ClubSummary::tier($tier)], 201);
    }

    public function updateTier(Request $request, string $tier, AuditLogger $audit): JsonResponse
    {
        $model = LoyaltyTier::query()->findOrFail($tier);
        $model->update($this->tierData($request, $model));
        $audit->record('loyalty.tier_updated', $model, $model->only(['name', 'min_spend', 'points_multiplier']));

        return response()->json(['data' => ClubSummary::tier($model)]);
    }

    public function destroyTier(string $tier, AuditLogger $audit): Response
    {
        $model = LoyaltyTier::query()->findOrFail($tier);

        if (LoyaltyAccount::query()->where('tier_id', $model->id)->exists()) {
            throw LoyaltyException::tierInUse();
        }

        $model->delete();
        $audit->record('loyalty.tier_deleted', $model, ['name' => $model->name]);

        return response()->noContent();
    }

    public function storeRule(Request $request, AuditLogger $audit): JsonResponse
    {
        $rule = CashbackRule::query()->create($this->ruleData($request));
        $audit->record('loyalty.cashback_rule_created', $rule, $rule->only(['name', 'kind', 'value', 'min_spend']));

        return response()->json(['data' => $this->rule($rule)], 201);
    }

    public function updateRule(Request $request, string $rule, AuditLogger $audit): JsonResponse
    {
        $model = CashbackRule::query()->findOrFail($rule);
        $model->update($this->ruleData($request));
        $audit->record('loyalty.cashback_rule_updated', $model, $model->only(['name', 'kind', 'value', 'min_spend', 'is_active']));

        return response()->json(['data' => $this->rule($model)]);
    }

    public function destroyRule(string $rule, AuditLogger $audit): Response
    {
        $model = CashbackRule::query()->findOrFail($rule);
        $model->delete();
        $audit->record('loyalty.cashback_rule_deleted', $model, ['name' => $model->name]);

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function present(): array
    {
        return [
            'settings' => collect(self::SETTING_KEYS)->mapWithKeys(fn (string $key) => [$key => TenantSettings::get($key)])->all(),
            'tiers' => LoyaltyTier::query()->orderBy('min_spend')->get()->map(fn (LoyaltyTier $t) => [
                ...ClubSummary::tier($t),
                'members' => LoyaltyAccount::query()->where('tier_id', $t->id)->count(),
            ])->values(),
            'cashback_rules' => CashbackRule::query()->orderBy('created_at')->get()->map(fn (CashbackRule $r) => $this->rule($r))->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function rule(CashbackRule $rule): array
    {
        return $rule->only(['id', 'name', 'category_id', 'min_spend', 'kind', 'value', 'max_reward', 'is_active']);
    }

    /** @return array<string, mixed> */
    private function tierData(Request $request, ?LoyaltyTier $tier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'min_spend' => ['required', 'integer', 'min:0', 'max:1000000000000', Rule::unique('loyalty_tiers', 'min_spend')->where('tenant_id', $tier->tenant_id ?? app(TenantContext::class)->id())->ignore($tier?->id)],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'points_multiplier' => ['sometimes', 'integer', 'between:0,100000'],
            'perks' => ['nullable', 'string', 'max:300'],
            'sort' => ['sometimes', 'integer', 'between:0,1000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function ruleData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category_id' => ['nullable', 'string', TenantExists::in('categories')],
            'min_spend' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'kind' => ['required', Rule::in(['fixed', 'percent'])],
            'value' => ['required', 'integer', 'min:1', $request->input('kind') === 'percent' ? 'max:10000' : 'max:1000000000'],
            'max_reward' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [...$data, 'category_id' => $data['category_id'] ?? null, 'max_reward' => $data['max_reward'] ?? null];
    }
}
