<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Support\FeatureCatalog;
use Illuminate\Http\JsonResponse;

/** The published plans for the «کافه‌یار برای کسب‌وکارها» page: names, prices (rial) and what each includes. */
final class PublicPlansController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'plans' => Plan::query()->where('is_public', true)->orderBy('sort')->get()->map(fn (Plan $p) => [
                'key' => $p->key,
                'name' => $p->name,
                'tagline' => $p->tagline,
                'monthly_price' => $p->monthly_price,
                'yearly_price' => $p->yearly_price,
                'features' => $p->features,
                'is_trial_plan' => $p->is_trial_plan,
            ])->values(),
            'features' => FeatureCatalog::all(),
            'vat_rate' => (int) config('billing.vat_rate', 10),
            'trial_days' => (int) config('billing.trial_days', 14),
        ]])->header('Cache-Control', 'public, max-age=300');
    }
}
