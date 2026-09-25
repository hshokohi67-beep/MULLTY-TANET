<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Actions\StartTrial;
use App\Modules\Billing\Enums\SubscriptionState;
use App\Modules\Billing\Models\EntitlementOverride;
use App\Modules\Billing\Models\Subscription;
use App\Support\Entitlements\EntitlementGate;
use App\Support\Entitlements\FeatureLabels;
use App\Support\Entitlements\PlanLimitReachedException;
use App\Support\Entitlements\SubscriptionReadOnlyException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * The real entitlement gate: plan values, then add-ons (+limits / switches on), then unexpired
 * platform overrides (replace). Bound request-scoped and memoised per tenant, so each request
 * reads the subscription at most once. Resolve at call time; never cache it in long-lived objects.
 */
final class Entitlements implements EntitlementGate
{
    /** Routes that keep working while read-only (besides reads): paying and leaving. */
    private const WRITABLE_WHEN_READ_ONLY = ['api.billing.', 'api.staff.logout', 'api.auth.'];

    /** @var array<string, array{subscription: Subscription, features: array<string, bool|int|null>}> */
    private array $memo = [];

    public function __construct(private readonly TenantContext $context) {}

    /** @return array{subscription: Subscription, state: SubscriptionState, features: array<string, bool|int|null>} */
    public function snapshot(): array
    {
        $tenant = $this->context->require();
        $entry = $this->memo[$tenant->id] ??= $this->build();

        // The state depends on the clock, so it is never memoised.
        return $entry + ['state' => SubscriptionState::of($entry['subscription'], now())];
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    public function enabled(string $feature): bool
    {
        if (! $this->context->has()) {
            return true;
        }

        return ($this->snapshot()['features'][$feature] ?? false) === true;
    }

    public function limit(string $feature): ?int
    {
        if (! $this->context->has()) {
            return null;
        }
        $value = $this->snapshot()['features'][$feature] ?? null;

        return is_int($value) ? $value : null;
    }

    public function ensureCanAdd(string $feature, int $currentCount): void
    {
        $limit = $this->limit($feature);
        if ($limit !== null && $currentCount >= $limit) {
            throw new PlanLimitReachedException($feature, FeatureLabels::of($feature), $limit);
        }
    }

    public function assertWritable(Request $request): void
    {
        if ($request->isMethodSafe() || ! $this->context->has() || $this->snapshot()['state']->writable()) {
            return;
        }

        $route = (string) $request->route()?->getName();
        foreach (self::WRITABLE_WHEN_READ_ONLY as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return;
            }
        }

        throw str_starts_with($route, 'api.public.') ? SubscriptionReadOnlyException::public() : SubscriptionReadOnlyException::staff();
    }

    /** @return array{subscription: Subscription, features: array<string, bool|int|null>} */
    private function build(): array
    {
        $subscription = Subscription::query()->with(['plan', 'addons.addon'])->first()
            ?? app(StartTrial::class)->handle()->load(['plan', 'addons.addon']);

        return ['subscription' => $subscription, 'features' => self::effective($subscription)];
    }

    /** @return array<string, bool|int|null> */
    public static function effective(Subscription $subscription): array
    {
        $features = [];
        foreach ([...FeatureCatalog::SWITCHES, ...FeatureCatalog::LIMITS] as $key) {
            $value = $subscription->plan->features[$key] ?? null;
            $features[$key] = FeatureCatalog::isSwitch($key) ? $value === true : (is_int($value) ? $value : null);
        }

        foreach ($subscription->addons as $item) {
            foreach ($item->addon->grants as $key => $grant) {
                if ($grant === true) {
                    $features[$key] = true;
                } elseif (is_int($grant) && is_int($features[$key] ?? null)) {
                    $features[$key] += $grant * $item->quantity; // unlimited stays unlimited
                }
            }
        }

        foreach (EntitlementOverride::query()->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get() as $override) {
            if (FeatureCatalog::exists($override->feature)) {
                $features[$override->feature] = FeatureCatalog::isSwitch($override->feature) ? $override->value === true : (is_int($override->value) ? $override->value : null);
            }
        }

        return $features;
    }
}
