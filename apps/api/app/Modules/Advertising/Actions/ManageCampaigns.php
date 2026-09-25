<?php

namespace App\Modules\Advertising\Actions;

use App\Modules\Advertising\Exceptions\AdException;
use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdPlacement;
use App\Modules\Advertising\Support\AdPricing;
use App\Modules\Advertising\Support\CampaignInvoices;
use App\Modules\Core\Models\Branch;
use App\Modules\Marketplace\Models\MarketplaceStore;
use App\Support\Audit\AuditLogger;
use App\Support\Media\ImageProcessor;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The café's side of a campaign: create/edit (re-quoted every time), creative image, send for
 * review, cancel before payment. Editing an approved campaign sends it back to review; any open
 * invoice is voided because the price may have changed.
 */
final class ManageCampaigns
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ImageProcessor $images,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, placement: string, start_date: string, days: int, cities?: list<string>, headline: string, body?: ?string, cta: string}  $v
     */
    public function save(?AdCampaign $campaign, array $v, ?string $userId): AdCampaign
    {
        if ($campaign !== null && ! in_array($campaign->status, AdCampaign::EDITABLE, true)) {
            throw AdException::notEditable();
        }
        $placement = AdPlacement::query()->where('key', $v['placement'])->where('is_active', true)->first() ?? throw AdException::placementUnavailable();
        $timezone = $this->context->require()->timezone;
        $this->assertStartNotPassed($v['start_date'], $timezone);
        $cities = $this->cities($v['cities'] ?? []);
        $quote = AdPricing::quote($placement, $v['start_date'], (int) $v['days'], $timezone, $campaign?->id);

        return DB::transaction(function () use ($campaign, $v, $cities, $quote, $userId): AdCampaign {
            $campaign ??= new AdCampaign(['status' => AdCampaign::DRAFT, 'created_by' => $userId]);
            if ($campaign->exists) {
                CampaignInvoices::assertNoPaymentInProgress($campaign);
                CampaignInvoices::voidOpen($campaign);
            } else {
                $campaign->ref = Str::random(16);
            }
            $campaign->fill([
                'name' => $v['name'],
                'placement' => $quote['placement'],
                'start_date' => $quote['start_date'],
                'days' => $quote['days'],
                'starts_at' => $quote['starts_at'],
                'ends_at' => $quote['ends_at'],
                'cities' => $cities,
                'headline' => $v['headline'],
                'body' => $v['body'] ?? null,
                'cta' => $v['cta'],
                'daily_price' => $quote['daily_price'],
                'amount' => $quote['subtotal'],
                'invoice_id' => null,
            ]);
            $this->backToReview($campaign);
            $campaign->save();
            $this->audit->record('ads.campaign_saved', $campaign, ['status' => $campaign->status, 'amount' => $campaign->amount]);

            return $campaign;
        });
    }

    public function uploadImage(AdCampaign $campaign, UploadedFile $file): AdCampaign
    {
        $this->assertEditable($campaign);
        try {
            $stored = $this->images->store($file, 'ads/'.$campaign->ref, [
                'wide' => ['fit' => 1600, 'quality' => 82],
                'small' => ['fit' => 800, 'quality' => 80],
            ]);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['image' => 'این تصویر قابل پردازش نیست. یک عکس JPG، PNG یا WebP دیگر انتخاب کنید.']);
        }
        $previous = array_filter([$campaign->image_path, $campaign->image_small_path]);
        $campaign->fill(['image_path' => $stored['wide']['path'], 'image_small_path' => $stored['small']['path']]);
        $this->backToReview($campaign);
        $campaign->save();
        Storage::disk(config('filesystems.media_disk'))->delete($previous);
        $this->audit->record('ads.image_uploaded', $campaign, []);

        return $campaign;
    }

    public function removeImage(AdCampaign $campaign): AdCampaign
    {
        $this->assertEditable($campaign);
        $previous = array_filter([$campaign->image_path, $campaign->image_small_path]);
        if ($previous !== []) {
            $campaign->fill(['image_path' => null, 'image_small_path' => null]);
            $this->backToReview($campaign);
            $campaign->save();
            Storage::disk(config('filesystems.media_disk'))->delete($previous);
        }

        return $campaign;
    }

    public function submit(AdCampaign $campaign): AdCampaign
    {
        if (! in_array($campaign->status, [AdCampaign::DRAFT, AdCampaign::REJECTED], true)) {
            throw AdException::wrongState();
        }
        $placement = AdPlacement::query()->where('key', $campaign->placement)->where('is_active', true)->first() ?? throw AdException::placementUnavailable();
        if ($placement->requires_image && $campaign->image_path === null) {
            throw AdException::imageRequired();
        }
        $timezone = $this->context->require()->timezone;
        $this->assertStartNotPassed($campaign->start_date->toDateString(), $timezone);
        if (! MarketplaceStore::query()->where('tenant_id', $campaign->tenant_id)->exists()) {
            throw AdException::notListed();
        }
        if (! AdPricing::quote($placement, $campaign->start_date->toDateString(), $campaign->days, $timezone, $campaign->id)['available']) {
            throw AdException::full();
        }

        $campaign->update(['status' => AdCampaign::PENDING, 'submitted_at' => now(), 'review_note' => null]);
        $this->audit->record('ads.campaign_submitted', $campaign, []);

        return $campaign;
    }

    public function cancel(AdCampaign $campaign): AdCampaign
    {
        $this->assertEditable($campaign);
        DB::transaction(function () use ($campaign): void {
            CampaignInvoices::assertNoPaymentInProgress($campaign);
            CampaignInvoices::voidOpen($campaign);
            $campaign->update(['status' => AdCampaign::CANCELLED]);
        });
        $this->audit->record('ads.campaign_cancelled', $campaign, []);

        return $campaign;
    }

    private function assertEditable(AdCampaign $campaign): void
    {
        if (! in_array($campaign->status, AdCampaign::EDITABLE, true)) {
            throw AdException::notEditable();
        }
    }

    /** A change after approval needs a fresh review (and a fresh invoice). */
    private function backToReview(AdCampaign $campaign): void
    {
        if ($campaign->status === AdCampaign::APPROVED) {
            $campaign->fill(['status' => AdCampaign::PENDING, 'submitted_at' => now(), 'reviewed_at' => null]);
        }
    }

    private function assertStartNotPassed(string $startDate, string $timezone): void
    {
        if (CarbonImmutable::parse($startDate, $timezone)->startOfDay()->lt(CarbonImmutable::now($timezone)->startOfDay())) {
            throw AdException::startPassed();
        }
    }

    /**
     * Only cities the café has an active branch in.
     *
     * @param  list<string>  $cities
     * @return list<string>
     */
    private function cities(array $cities): array
    {
        $cities = array_values(array_unique(array_map('trim', $cities)));
        $own = Branch::query()->where('is_active', true)->whereNotNull('city')->pluck('city')->map(fn ($c) => trim((string) $c))->all();
        foreach ($cities as $city) {
            if (! in_array($city, $own, true)) {
                throw AdException::unknownCity();
            }
        }

        return $cities;
    }
}
