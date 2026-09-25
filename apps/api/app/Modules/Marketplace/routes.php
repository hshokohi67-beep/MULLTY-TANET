<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Marketplace\Http\Controllers\ListingController;
use App\Modules\Marketplace\Http\Controllers\PlatformMarketplaceController;
use App\Modules\Marketplace\Http\Controllers\PublicMarketplaceController;
use Illuminate\Support\Facades\Route;

// Public marketplace: no tenant; reads only the public projection.
Route::middleware('throttle:public')->prefix('public/marketplace')->name('public.marketplace.')->group(function () {
    Route::get('home', [PublicMarketplaceController::class, 'home'])->name('home');
    Route::get('stores', [PublicMarketplaceController::class, 'stores'])->name('stores');
    Route::get('suggest', [PublicMarketplaceController::class, 'suggest'])->name('suggest');
    Route::get('stores/{storeSlug}', [PublicMarketplaceController::class, 'show'])->where('storeSlug', '[A-Za-z0-9-]{2,64}')->name('stores.show');
});

// The café's own entry.
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'can:'.P::MARKETPLACE_MANAGE])->prefix('marketplace')->name('marketplace.')->group(function () {
    Route::get('listing', [ListingController::class, 'show'])->name('listing.show');
    Route::put('listing', [ListingController::class, 'update'])->name('listing.update');
});

// Platform moderation.
Route::middleware(['auth:sanctum', 'actor:platform'])->prefix('platform/marketplace')->name('platform.marketplace.')->group(function () {
    Route::get('/', [PlatformMarketplaceController::class, 'index'])->name('index');
    Route::post('{tenant}/hide', [PlatformMarketplaceController::class, 'hide'])->name('hide');
    Route::post('{tenant}/unhide', [PlatformMarketplaceController::class, 'unhide'])->name('unhide');
    Route::post('{tenant}/feature', [PlatformMarketplaceController::class, 'feature'])->name('feature');
});
