<?php

namespace App\Modules\Insights\Http\Controllers;

use App\Modules\Insights\Support\Alerts;
use App\Modules\Insights\Support\Overview;
use App\Support\Realtime\LiveVersion;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The management overview. Figures are cached briefly, keyed by the order board's live version,
 * so any real change is reflected at once while repeated views cost a cache read. Each section
 * is included only if the viewer may see its data.
 */
final class OverviewController
{
    public function show(Request $request, TenantContext $context): JsonResponse
    {
        $v = $request->validate([
            'range' => ['nullable', Rule::in(Overview::RANGES)],
            'branch_id' => ['nullable', 'string', 'max:26'],
        ]);
        $range = $v['range'] ?? 'today';
        $branchId = $v['branch_id'] ?? null;
        $tenant = $context->require();
        $can = fn (string $ability) => Gate::allows($ability);

        $sections = array_filter([
            'sales' => $can('orders.view'),
            'payments' => $can('payments.view'),
            'customers' => $can('customers.view'),
        ]);

        // The minute keeps time-based parts (late orders, "same time yesterday") honest.
        $key = sprintf('overview:%s:%s:%s:%s:%s:%d', $tenant->id, $branchId ?? 'all', $range, implode(',', array_keys($sections)), LiveVersion::get(LiveVersion::orders($tenant->id)), intdiv(time(), 60));

        $data = Cache::remember($key, 90, function () use ($tenant, $branchId, $range, $sections): array {
            $overview = new Overview($tenant->timezone, $branchId, CarbonImmutable::now());

            return array_filter([
                'generated_at' => now()->toIso8601String(),
                'kpis' => isset($sections['sales']) ? $overview->kpis($range) : null,
                'series' => isset($sections['sales']) ? $overview->series($range) : null,
                'trend' => isset($sections['sales']) ? $overview->trend() : null,
                'live' => isset($sections['sales']) ? $overview->live() : null,
                'top_products' => isset($sections['sales']) ? $overview->topProducts($range) : null,
                'payment_mix' => isset($sections['payments']) ? $overview->paymentMix($range) : null,
                'customers' => isset($sections['customers']) ? $overview->customers($range) : null,
            ], fn ($section) => $section !== null);
        });

        return response()->json(['data' => [...$data, 'alerts' => Alerts::for($branchId, $can)]]);
    }
}
