<?php

use App\Modules\Advertising\Http\Controllers\AdCampaignController;
use App\Modules\Advertising\Http\Controllers\PlatformAdsController;
use App\Modules\Advertising\Http\Controllers\PublicAdEventController;
use App\Modules\Identity\Support\PermissionCatalog as P;
use Illuminate\Support\Facades\Route;

// Impression/click beacons (signed tokens; forwarded visitor IP).
Route::post('public/ads/events', [PublicAdEventController::class, 'store'])->middleware('throttle:ad-events')->name('public.ads.events');

// The café's campaigns.
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'can:'.P::ADS_MANAGE])->prefix('ads')->name('ads.')->group(function () {
    Route::get('/', [AdCampaignController::class, 'index'])->name('index');
    Route::post('quote', [AdCampaignController::class, 'quote'])->name('quote');
    Route::post('campaigns', [AdCampaignController::class, 'store'])->name('campaigns.store');
    Route::get('campaigns/{adCampaign}', [AdCampaignController::class, 'show'])->name('campaigns.show');
    Route::put('campaigns/{adCampaign}', [AdCampaignController::class, 'update'])->name('campaigns.update');
    Route::post('campaigns/{adCampaign}/image', [AdCampaignController::class, 'uploadImage'])->middleware('throttle:uploads')->name('campaigns.image');
    Route::delete('campaigns/{adCampaign}/image', [AdCampaignController::class, 'removeImage'])->name('campaigns.image.destroy');
    Route::post('campaigns/{adCampaign}/submit', [AdCampaignController::class, 'submit'])->name('campaigns.submit');
    Route::post('campaigns/{adCampaign}/cancel', [AdCampaignController::class, 'cancel'])->name('campaigns.cancel');
    Route::post('campaigns/{adCampaign}/pay', [AdCampaignController::class, 'pay'])->middleware('throttle:billing-checkout')->name('campaigns.pay');
    Route::post('invoices/{billingInvoice}/verify', [AdCampaignController::class, 'verify'])->middleware('throttle:billing-checkout')->name('invoices.verify');
});

// Platform review and pricing.
Route::middleware(['auth:sanctum', 'actor:platform'])->prefix('platform/ads')->name('platform.ads.')->group(function () {
    Route::get('/', [PlatformAdsController::class, 'index'])->name('index');
    Route::post('{campaignId}/approve', [PlatformAdsController::class, 'approve'])->where('campaignId', '[0-9A-Za-z]{26}')->name('approve');
    Route::post('{campaignId}/reject', [PlatformAdsController::class, 'reject'])->where('campaignId', '[0-9A-Za-z]{26}')->name('reject');
    Route::post('{campaignId}/suspend', [PlatformAdsController::class, 'suspend'])->where('campaignId', '[0-9A-Za-z]{26}')->name('suspend');
    Route::post('{campaignId}/resume', [PlatformAdsController::class, 'resume'])->where('campaignId', '[0-9A-Za-z]{26}')->name('resume');
    Route::put('placements/{key}', [PlatformAdsController::class, 'updatePlacement'])->where('key', '[a-z_]{3,24}')->name('placements.update');
});
