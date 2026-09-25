<?php

namespace App\Support\Entitlements;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware `feature:<key>`: the tenant's plan must include the feature. */
final class RequireFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $gate = app(EntitlementGate::class);
        if (! $gate->enabled($feature)) {
            throw new FeatureNotInPlanException($feature, FeatureLabels::of($feature));
        }

        return $next($request);
    }
}
