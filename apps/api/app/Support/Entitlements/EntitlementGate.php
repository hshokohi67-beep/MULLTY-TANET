<?php

namespace App\Support\Entitlements;

use Illuminate\Http\Request;

/**
 * What the current tenant's subscription allows. Defined here so every module can ask without
 * depending on Billing; Billing binds the real implementation (request-scoped). Feature keys are
 * listed in `Billing\Support\FeatureCatalog`.
 */
interface EntitlementGate
{
    /** An on/off feature (e.g. "inventory"). */
    public function enabled(string $feature): bool;

    /** A limit (e.g. "branches"); null means unlimited. */
    public function limit(string $feature): ?int;

    /**
     * Throws when adding one more would exceed the limit.
     *
     * @throws PlanLimitReachedException
     */
    public function ensureCanAdd(string $feature, int $currentCount): void;

    /**
     * Throws for writes while the subscription is read-only (reads and billing always work).
     *
     * @throws SubscriptionReadOnlyException
     */
    public function assertWritable(Request $request): void;
}
