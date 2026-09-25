<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Modules\Advertising\Actions\ReviewCampaign;
use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdDailyStat;
use App\Modules\Advertising\Models\AdPlacement;
use App\Modules\Advertising\Support\AdPresenter;
use App\Modules\Core\Models\Tenant;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Platform side of advertising: review queue, all campaigns, suspend/resume, placement prices. */
final class PlatformAdsController
{
    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'paid', 'suspended', 'cancelled', 'issues'])]])['status'] ?? null;

        // Platform view across tenants: bypass is the point here (platform actor only).
        [$campaigns, $totals, $counts] = $context->bypass(function () use ($status): array {
            $campaigns = AdCampaign::query()
                ->when($status === 'issues', fn ($q) => $q->whereNotNull('payment_issue'))
                ->when($status && $status !== 'issues', fn ($q) => $q->where('status', $status))
                // Drafts are the café's private work: never shown to the platform.
                ->where('status', '!=', AdCampaign::DRAFT)
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderByDesc('updated_at')->limit(200)->get();
            $totals = AdDailyStat::query()->whereIn('campaign_id', $campaigns->modelKeys())
                ->selectRaw('campaign_id, SUM(impressions) as i, SUM(clicks) as c')->groupBy('campaign_id')->get()
                ->mapWithKeys(fn (AdDailyStat $r) => [(string) $r->campaign_id => ['impressions' => (int) $r->getAttribute('i'), 'clicks' => (int) $r->getAttribute('c')]]);
            $counts = AdCampaign::query()->where('status', '!=', AdCampaign::DRAFT)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all();
            $counts['issues'] = AdCampaign::query()->whereNotNull('payment_issue')->count();

            return [$campaigns, $totals, $counts];
        });
        $tenants = Tenant::query()->whereIn('id', $campaigns->pluck('tenant_id')->unique())->get(['id', 'name', 'slug'])->keyBy('id');

        return response()->json(['data' => [
            'campaigns' => $campaigns->map(function (AdCampaign $c) use ($tenants, $totals): array {
                $tenant = $tenants->get($c->tenant_id);

                return ['tenant' => ['name' => $tenant?->name, 'slug' => $tenant?->slug]] + AdPresenter::campaign($c, $totals[$c->id] ?? ['impressions' => 0, 'clicks' => 0]);
            })->values(),
            'counts' => $counts,
            'placements' => AdPlacement::query()->orderBy('sort')->get()->map(fn (AdPlacement $p) => AdPresenter::placement($p))->values(),
        ]]);
    }

    public function approve(string $campaignId, TenantContext $context, ReviewCampaign $review): JsonResponse
    {
        return $this->act($campaignId, $context, fn (AdCampaign $c) => $review->approve($c));
    }

    public function reject(Request $request, string $campaignId, TenantContext $context, ReviewCampaign $review): JsonResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];

        return $this->act($campaignId, $context, fn (AdCampaign $c) => $review->reject($c, $reason));
    }

    public function suspend(Request $request, string $campaignId, TenantContext $context, ReviewCampaign $review): JsonResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];

        return $this->act($campaignId, $context, fn (AdCampaign $c) => $review->suspend($c, $reason));
    }

    public function resume(string $campaignId, TenantContext $context, ReviewCampaign $review): JsonResponse
    {
        return $this->act($campaignId, $context, fn (AdCampaign $c) => $review->resume($c));
    }

    public function updatePlacement(Request $request, string $key, AuditLogger $audit): JsonResponse
    {
        $placement = AdPlacement::query()->where('key', $key)->firstOrFail();
        $v = $request->validate([
            'daily_price' => ['required', 'integer', 'min:10000', 'max:10000000000'],
            'capacity' => ['required', 'integer', 'between:1,50'],
            'is_active' => ['required', 'boolean'],
        ]);
        $placement->update($v);
        $audit->record('platform.ad_placement_updated', $placement, $v);

        return response()->json(['data' => AdPresenter::placement($placement)]);
    }

    /** @param  callable(AdCampaign): AdCampaign  $action */
    private function act(string $campaignId, TenantContext $context, callable $action): JsonResponse
    {
        // Find the campaign's café first (platform actor, no tenant yet), then act inside it.
        $tenantId = $context->bypass(fn () => AdCampaign::query()->whereKey($campaignId)->value('tenant_id'));
        $tenant = Tenant::query()->findOrFail($tenantId);

        $data = $context->runAs($tenant, fn () => AdPresenter::campaign($action(AdCampaign::query()->findOrFail($campaignId))));

        return response()->json(['data' => $data]);
    }
}
