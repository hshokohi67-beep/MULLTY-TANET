<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\ManageBilling;
use App\Modules\Billing\Enums\SubscriptionState;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\EntitlementOverride;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\BillingPresenter;
use App\Modules\Billing\Support\Entitlements;
use App\Modules\Billing\Support\FeatureCatalog;
use App\Modules\Core\Models\Tenant;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Platform admin: every café's subscription, plan editing, trial extensions, grants, transfers. */
final class PlatformBillingController
{
    public function subscriptions(TenantContext $context): JsonResponse
    {
        // Platform view across tenants: bypass is the point here (platform actor only).
        $rows = $context->bypass(fn () => Subscription::query()->with(['plan', 'addons.addon'])->get()->keyBy('tenant_id'));
        $open = $context->bypass(fn () => BillingInvoice::query()->where('status', 'open')->get()->groupBy('tenant_id'));
        $overrides = $context->bypass(fn () => EntitlementOverride::query()->get()->groupBy('tenant_id'));

        $data = Tenant::query()->orderBy('created_at')->get()->map(function (Tenant $t) use ($rows, $open, $overrides): array {
            $s = $rows->get($t->id);
            $state = $s ? SubscriptionState::of($s, now()) : null;

            return [
                'tenant' => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'status' => $t->status->value, 'created_at' => $t->created_at?->toIso8601String()],
                'subscription' => $s ? [
                    'plan' => ['key' => $s->plan->key, 'name' => $s->plan->name], 'cycle' => $s->cycle, 'status' => $s->status,
                    'state' => $state?->value, 'state_label' => $state?->label(), 'ends_at' => $s->endsAt()?->toIso8601String(),
                    'addons' => $s->addons->map(fn ($a) => ['name' => $a->addon->name, 'quantity' => $a->quantity])->values()->all(),
                ] : null,
                'open_invoices' => ($open->get($t->id) ?? collect())->map(fn (BillingInvoice $i) => ['id' => $i->id, 'number' => $i->number, 'total' => $i->total, 'kind' => $i->kind])->values()->all(),
                'overrides' => ($overrides->get($t->id) ?? collect())->map(fn (EntitlementOverride $o) => ['feature' => $o->feature, 'value' => $o->value, 'reason' => $o->reason, 'expires_at' => $o->expires_at?->toIso8601String()])->values()->all(),
            ];
        });

        return response()->json(['data' => $data->values()]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['data' => [
            'plans' => Plan::query()->orderBy('sort')->get()->map(fn (Plan $p) => BillingPresenter::plan($p))->values(),
            'features' => FeatureCatalog::all(),
        ]]);
    }

    public function updatePlan(Request $request, Plan $plan, AuditLogger $audit): JsonResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'monthly_price' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'yearly_price' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'is_public' => ['required', 'boolean'],
            'features' => ['required', 'array'],
        ];
        foreach (FeatureCatalog::SWITCHES as $key) {
            $rules["features.{$key}"] = ['required', 'boolean'];
        }
        foreach (FeatureCatalog::LIMITS as $key) {
            $rules["features.{$key}"] = ['present', 'nullable', 'integer', 'min:0', 'max:1000000'];
        }
        $v = $request->validate($rules);
        $features = [];
        foreach ([...FeatureCatalog::SWITCHES, ...FeatureCatalog::LIMITS] as $key) {
            $features[$key] = FeatureCatalog::isSwitch($key) ? (bool) $v['features'][$key] : ($v['features'][$key] === null ? null : (int) $v['features'][$key]);
        }
        $plan->update([...$v, 'features' => $features]);
        $audit->record('platform.plan_updated', $plan, ['key' => $plan->key]);

        return response()->json(['data' => BillingPresenter::plan($plan)]);
    }

    /** Adds days to the trial or the paid period (a goodwill extension or a manual fix). */
    public function extend(Request $request, Tenant $tenant, TenantContext $context, AuditLogger $audit): JsonResponse
    {
        $v = $request->validate(['days' => ['required', 'integer', 'between:1,365'], 'reason' => ['required', 'string', 'max:200']]);

        $data = $context->runAs($tenant, function () use ($v, $audit): array {
            $s = Subscription::query()->with(['plan', 'addons.addon'])->firstOrFail();
            $from = CarbonImmutable::now()->max($s->endsAt() ? CarbonImmutable::instance($s->endsAt()) : CarbonImmutable::now());
            $s->status === 'trialing'
                ? $s->update(['trial_ends_at' => $from->addDays($v['days']), 'reminder_stage' => null])
                : $s->update(['current_period_end' => $from->addDays($v['days']), 'status' => 'active', 'cancelled_at' => null, 'reminder_stage' => null, 'current_period_start' => $s->current_period_start ?? now()]);
            $audit->record('platform.subscription_extended', $s, $v);

            return ['ends_at' => $s->refresh()->endsAt()?->toIso8601String(), 'state' => SubscriptionState::of($s, now())->value];
        });

        return response()->json(['data' => $data]);
    }

    public function override(Request $request, Tenant $tenant, TenantContext $context, AuditLogger $audit): JsonResponse
    {
        $v = $request->validate([
            'feature' => ['required', Rule::in([...FeatureCatalog::SWITCHES, ...FeatureCatalog::LIMITS])],
            'value' => ['present', 'nullable'],
            'reason' => ['required', 'string', 'max:200'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $value = $v['value'];
        if (FeatureCatalog::isSwitch($v['feature']) ? ! is_bool($value) : ! ($value === null || (is_int($value) && $value >= 0))) {
            throw BillingException::unknownFeature();
        }

        $context->runAs($tenant, function () use ($v, $value, $request, $audit): void {
            $o = EntitlementOverride::query()->updateOrCreate(['feature' => $v['feature']], [
                'value' => $value, 'reason' => $v['reason'], 'expires_at' => $v['expires_at'] ?? null, 'granted_by' => $request->user()?->getAuthIdentifier(),
            ]);
            $audit->record('platform.override_set', $o, ['feature' => $v['feature'], 'value' => $value]);
        });

        return response()->json(['data' => ['feature' => $v['feature'], 'value' => $value]], 201);
    }

    public function removeOverride(Tenant $tenant, string $feature, TenantContext $context): Response
    {
        $context->runAs($tenant, fn () => EntitlementOverride::query()->where('feature', $feature)->delete());

        return response()->noContent();
    }

    /** A bank transfer confirmed by the platform: the invoice is paid and applied. */
    public function markPaid(Request $request, Tenant $tenant, string $invoiceId, TenantContext $context, ManageBilling $billing): JsonResponse
    {
        $v = $request->validate(['reference' => ['required', 'string', 'max:100']]);

        $data = $context->runAs($tenant, function () use ($invoiceId, $v, $billing): array {
            $invoice = BillingInvoice::query()->findOrFail($invoiceId);
            if ($invoice->status !== 'open') {
                throw BillingException::invoiceNotPayable();
            }
            $billing->markPaid($invoice, 'transfer', $v['reference']);

            return BillingPresenter::invoice($invoice->refresh()->load('plan'));
        });

        return response()->json(['data' => $data]);
    }

    /** Effective features of one café (support view). */
    public function entitlements(Tenant $tenant, TenantContext $context): JsonResponse
    {
        return response()->json(['data' => $context->runAs($tenant, fn () => Entitlements::effective(Subscription::query()->with(['plan', 'addons.addon'])->firstOrFail()))]);
    }
}
