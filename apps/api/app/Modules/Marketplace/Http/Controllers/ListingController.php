<?php

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Marketplace\Actions\ProjectStore;
use App\Modules\Marketplace\Http\Requests\ListingRequest;
use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Marketplace\Models\MarketplaceStore;
use App\Modules\Marketplace\Support\MarketplaceCatalog;
use App\Modules\Marketplace\Support\StorePresenter;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/** The café's marketplace entry (`marketplace.manage`): fields, eligibility checklist, public preview. */
final class ListingController
{
    public function show(ProjectStore $project, TenantContext $context): JsonResponse
    {
        return response()->json(['data' => $this->payload($project->handle(), $context)]);
    }

    public function update(ListingRequest $request, ProjectStore $project, TenantContext $context, AuditLogger $audit): JsonResponse
    {
        $listing = MarketplaceListing::query()->firstOrNew();
        $v = $request->validated();
        $listing->fill([
            'is_listed' => (bool) $v['is_listed'],
            'headline' => $v['headline'] ?? null,
            'about' => $v['about'] ?? null,
            'categories' => array_values($v['categories']),
            'amenities' => array_values($v['amenities']),
            'price_level' => $v['price_level'] ?? null,
        ]);
        if ($listing->is_listed && $listing->listed_at === null) {
            $listing->listed_at = now();
        }
        $listing->save();
        $audit->record('marketplace.listing_updated', $listing, ['is_listed' => $listing->is_listed]);

        return response()->json(['data' => $this->payload($project->handle(), $context)]);
    }

    /**
     * @param  array{eligible: bool, checks: list<array{key: string, label: string, ok: bool, required: bool}>}  $status
     * @return array<string, mixed>
     */
    private function payload(array $status, TenantContext $context): array
    {
        $listing = MarketplaceListing::query()->first();
        $tenant = $context->require();
        $rows = MarketplaceStore::query()->where('tenant_id', $tenant->id)->orderBy('created_at')->get();

        return [
            'listing' => [
                'is_listed' => (bool) $listing?->is_listed,
                'headline' => $listing?->headline,
                'about' => $listing?->about,
                'categories' => $listing->categories ?? [],
                'amenities' => $listing->amenities ?? [],
                'price_level' => $listing?->price_level,
                'hidden_reason' => $listing?->hidden_at ? $listing->hidden_reason : null,
            ],
            'eligible' => $status['eligible'],
            'checks' => $status['checks'],
            'preview' => $rows->isEmpty() ? null : StorePresenter::card($rows->first()),
            'public_path' => $rows->isEmpty() ? null : '/explore/'.$tenant->slug,
            'catalog' => [
                'categories' => MarketplaceCatalog::labelled(array_keys(MarketplaceCatalog::CATEGORIES), MarketplaceCatalog::CATEGORIES),
                'amenities' => MarketplaceCatalog::labelled(array_keys(MarketplaceCatalog::AMENITIES), MarketplaceCatalog::AMENITIES),
                'price_levels' => array_map(fn (int $k, string $l) => ['key' => $k, 'label' => $l], array_keys(MarketplaceCatalog::PRICE_LEVELS), MarketplaceCatalog::PRICE_LEVELS),
                'max_categories' => MarketplaceCatalog::MAX_CATEGORIES,
            ],
        ];
    }
}
