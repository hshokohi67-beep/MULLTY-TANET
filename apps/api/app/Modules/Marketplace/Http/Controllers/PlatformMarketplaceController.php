<?php

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Core\Models\Tenant;
use App\Modules\Marketplace\Actions\ManagePlaceImages;
use App\Modules\Marketplace\Actions\ProjectStore;
use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Marketplace\Models\MarketplacePlaceImage;
use App\Modules\Marketplace\Models\MarketplaceStore;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Platform moderation of the marketplace: hide/unhide a café, feature it until a date, city tile photos. */
final class PlatformMarketplaceController
{
    public function index(TenantContext $context): JsonResponse
    {
        // Platform view across tenants: bypass is the point here (platform actor only).
        $listings = $context->bypass(fn () => MarketplaceListing::query()->get()->keyBy('tenant_id'));
        $live = MarketplaceStore::query()->selectRaw('tenant_id, COUNT(*) as n')->groupBy('tenant_id')->pluck('n', 'tenant_id');

        $data = Tenant::query()->whereIn('id', $listings->keys())->orderBy('name')->get()->map(function (Tenant $t) use ($listings, $live): array {
            /** @var MarketplaceListing $l */
            $l = $listings->get($t->id);

            return [
                'tenant' => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug],
                'is_listed' => $l->is_listed,
                'headline' => $l->headline,
                'live_branches' => (int) ($live[$t->id] ?? 0),
                'hidden_at' => $l->hidden_at?->toIso8601String(),
                'hidden_reason' => $l->hidden_reason,
                'featured_until' => $l->featured_until?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $data->values()]);
    }

    public function hide(Request $request, Tenant $tenant, TenantContext $context, AuditLogger $audit): JsonResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];

        return $this->update($tenant, $context, $audit, ['hidden_at' => now(), 'hidden_reason' => $reason], 'platform.marketplace_hidden');
    }

    public function unhide(Tenant $tenant, TenantContext $context, AuditLogger $audit): JsonResponse
    {
        return $this->update($tenant, $context, $audit, ['hidden_at' => null, 'hidden_reason' => null], 'platform.marketplace_unhidden');
    }

    public function feature(Request $request, Tenant $tenant, TenantContext $context, AuditLogger $audit): JsonResponse
    {
        $until = $request->validate(['until' => ['present', 'nullable', 'date', 'after:now']])['until'];

        return $this->update($tenant, $context, $audit, ['featured_until' => $until], 'platform.marketplace_featured');
    }

    /** Cities that have stores, with the tile photo the platform designed for each. */
    public function places(): JsonResponse
    {
        $images = MarketplacePlaceImage::query()->get()->keyBy('city');
        $data = MarketplaceStore::query()->selectRaw('city, province, COUNT(DISTINCT store_slug) as n')->groupBy('city', 'province')->orderByDesc('n')->get()
            ->map(function (MarketplaceStore $row) use ($images): array {
                $image = $images->get($row->city);

                return ['city' => $row->city, 'province' => $row->province, 'stores' => (int) $row->getAttribute('n'), 'image_url' => $image?->url(), 'image_wide_url' => $image?->wideUrl()];
            });

        return response()->json(['data' => $data->values()]);
    }

    public function uploadPlaceImage(Request $request, ManagePlaceImages $manage): JsonResponse
    {
        $v = $request->validate([
            'city' => ['required', 'string', 'max:60'],
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:6144', 'dimensions:min_width=480,min_height=320'],
        ]);
        $city = $this->knownCity((string) $v['city']);
        $image = $manage->upload($city, $request->file('image'));

        return response()->json(['data' => ['city' => $image->city, 'image_url' => $image->url(), 'image_wide_url' => $image->wideUrl()]]);
    }

    public function removePlaceImage(Request $request, ManagePlaceImages $manage): JsonResponse
    {
        $city = (string) $request->validate(['city' => ['required', 'string', 'max:60']])['city'];
        $manage->remove(trim($city));

        return response()->json(['data' => ['city' => trim($city), 'image_url' => null]]);
    }

    /** Only cities that actually have stores in the marketplace can get a photo. */
    private function knownCity(string $city): string
    {
        $city = trim($city);
        if (! MarketplaceStore::query()->where('city', $city)->exists()) {
            throw ValidationException::withMessages(['city' => 'این شهر هنوز فروشگاهی در خوراک‌گردی ندارد.']);
        }

        return $city;
    }

    /** @param  array<string, mixed>  $changes */
    private function update(Tenant $tenant, TenantContext $context, AuditLogger $audit, array $changes, string $action): JsonResponse
    {
        $status = $context->runAs($tenant, function () use ($changes, $audit, $action): array {
            $listing = MarketplaceListing::query()->firstOrCreate([]);
            $listing->update($changes);
            $audit->record($action, $listing, $changes);

            return app(ProjectStore::class)->handle();
        });

        return response()->json(['data' => ['eligible' => $status['eligible']]]);
    }
}
