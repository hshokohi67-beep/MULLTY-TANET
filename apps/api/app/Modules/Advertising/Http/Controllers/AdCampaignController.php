<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Modules\Advertising\Actions\ManageCampaigns;
use App\Modules\Advertising\Actions\PayCampaign;
use App\Modules\Advertising\Exceptions\AdException;
use App\Modules\Advertising\Http\Requests\CampaignRequest;
use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdPlacement;
use App\Modules\Advertising\Support\AdPresenter;
use App\Modules\Advertising\Support\AdPricing;
use App\Modules\Advertising\Support\AdStats;
use App\Modules\Advertising\Support\CampaignInvoices;
use App\Modules\Billing\Actions\ManageBilling;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Core\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The café's ads (`ads.manage`): campaigns, quotes, creative, review and payment. */
final class AdCampaignController
{
    public function index(TenantContext $context): JsonResponse
    {
        $campaigns = AdCampaign::query()->orderByDesc('created_at')->limit(100)->get();
        $totals = AdStats::totals($campaigns->modelKeys());
        $series = AdStats::series($context->require()->timezone);

        return response()->json(['data' => [
            'summary' => AdStats::summary($series),
            'series' => $series,
            'campaigns' => $campaigns->map(fn (AdCampaign $c) => AdPresenter::campaign($c, $totals[$c->id] ?? ['impressions' => 0, 'clicks' => 0]))->values(),
            'placements' => AdPlacement::query()->where('is_active', true)->orderBy('sort')->get()->map(fn (AdPlacement $p) => AdPresenter::placement($p))->values(),
            'cities' => Branch::query()->where('is_active', true)->whereNotNull('city')->pluck('city')->map(fn ($c) => trim((string) $c))->unique()->values(),
            'ctas' => collect(AdCampaign::CTAS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values(),
            'vat_rate' => (int) config('billing.vat_rate', 10),
        ]]);
    }

    public function quote(Request $request, TenantContext $context): JsonResponse
    {
        $v = $request->validate([
            'placement' => ['required', 'string', 'max:24'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'days' => ['required', 'integer', 'between:1,'.AdCampaign::MAX_DAYS],
            'campaign' => ['nullable', 'string', 'size:26'],
        ]);
        $placement = AdPlacement::query()->where('key', $v['placement'])->where('is_active', true)->first() ?? throw AdException::placementUnavailable();
        $except = isset($v['campaign']) ? AdCampaign::query()->whereKey($v['campaign'])->value('id') : null;
        $q = AdPricing::quote($placement, (string) $v['start_date'], (int) $v['days'], $context->require()->timezone, $except);

        return response()->json(['data' => [
            'daily_price' => $q['daily_price'], 'days' => $q['days'], 'subtotal' => $q['subtotal'], 'vat_rate' => $q['vat_rate'], 'vat' => $q['vat'], 'total' => $q['total'],
            'starts_at' => $q['starts_at']->toIso8601String(), 'ends_at' => $q['ends_at']->toIso8601String(),
            'available' => $q['available'], 'remaining' => max(0, $q['capacity'] - $q['taken']),
        ]]);
    }

    public function show(AdCampaign $adCampaign, TenantContext $context): JsonResponse
    {
        $totals = AdStats::totals([$adCampaign->id]);

        return response()->json(['data' => [
            'campaign' => AdPresenter::campaign($adCampaign, $totals[$adCampaign->id] ?? ['impressions' => 0, 'clicks' => 0]),
            'series' => AdStats::series($context->require()->timezone, 30, $adCampaign->id),
        ]]);
    }

    public function store(CampaignRequest $request, ManageCampaigns $manage): JsonResponse
    {
        $campaign = $manage->save(null, $request->campaign(), (string) $request->user()?->getAuthIdentifier());

        return response()->json(['data' => AdPresenter::campaign($campaign)], 201);
    }

    public function update(CampaignRequest $request, AdCampaign $adCampaign, ManageCampaigns $manage): JsonResponse
    {
        return response()->json(['data' => AdPresenter::campaign($manage->save($adCampaign, $request->campaign(), null))]);
    }

    public function uploadImage(Request $request, AdCampaign $adCampaign, ManageCampaigns $manage): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:6144', 'dimensions:min_width=960,min_height=360']]);

        return response()->json(['data' => AdPresenter::campaign($manage->uploadImage($adCampaign, $request->file('image')))]);
    }

    public function removeImage(AdCampaign $adCampaign, ManageCampaigns $manage): JsonResponse
    {
        return response()->json(['data' => AdPresenter::campaign($manage->removeImage($adCampaign))]);
    }

    public function submit(AdCampaign $adCampaign, ManageCampaigns $manage): JsonResponse
    {
        return response()->json(['data' => AdPresenter::campaign($manage->submit($adCampaign))]);
    }

    public function cancel(AdCampaign $adCampaign, ManageCampaigns $manage): JsonResponse
    {
        return response()->json(['data' => AdPresenter::campaign($manage->cancel($adCampaign))]);
    }

    public function pay(Request $request, AdCampaign $adCampaign, PayCampaign $pay): JsonResponse
    {
        return response()->json(['data' => ['redirect_url' => $pay->handle($adCampaign, (string) $request->user()?->getAuthIdentifier())]]);
    }

    /** The gateway return for an ad invoice (billing.manage isn't needed to pay for ads). */
    public function verify(Request $request, BillingInvoice $billingInvoice, ManageBilling $billing): JsonResponse
    {
        if ($billingInvoice->kind !== CampaignInvoices::KIND) {
            throw new NotFoundHttpException;
        }
        $authority = (string) $request->validate(['authority' => ['required', 'string', 'max:64']])['authority'];
        $paid = $billing->verify($billingInvoice, $authority);

        return response()->json(['data' => ['paid' => $paid, 'campaign' => $billingInvoice->subject_id]]);
    }
}
