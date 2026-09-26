<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Storefront\Http\Controllers\LandingController;
use App\Modules\Storefront\Http\Controllers\PublicLandingController;
use App\Modules\Storefront\Http\Controllers\PublicStoryController;
use App\Modules\Storefront\Http\Controllers\StoryController;
use Illuminate\Support\Facades\Route;

// Storefront (guests): the landing page, live stories and anonymous view/click counting.
Route::middleware(['tenant'])->prefix('public')->name('public.')->group(function () {
    Route::get('landing', [PublicLandingController::class, 'show'])->middleware('throttle:public')->name('landing.show');
    Route::get('stories', [PublicStoryController::class, 'index'])->middleware('throttle:public')->name('stories.index');
    Route::post('stories/{storyId}/seen', [PublicStoryController::class, 'seen'])->middleware('throttle:story-events')->name('stories.seen');
    Route::post('stories/{storyId}/click', [PublicStoryController::class, 'click'])->middleware('throttle:story-events')->name('stories.click');
});

// Dashboard. Updates are POST because they carry an optional multipart image.
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'can:'.P::STOREFRONT_MANAGE, 'feature:stories'])->name('stories.')->group(function () {
    Route::get('stories', [StoryController::class, 'index'])->name('index');
    Route::post('stories', [StoryController::class, 'store'])->middleware('throttle:uploads')->name('store');
    Route::put('stories/order', [StoryController::class, 'reorder'])->name('reorder');
    Route::post('stories/{story}', [StoryController::class, 'update'])->middleware('throttle:uploads')->name('update');
    Route::delete('stories/{story}', [StoryController::class, 'destroy'])->name('destroy');
});

// The landing page editor (every plan). Media uploads are multipart POSTs.
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'can:'.P::STOREFRONT_MANAGE])->prefix('storefront/landing')->name('landing.')->group(function () {
    Route::get('/', [LandingController::class, 'show'])->name('show');
    Route::put('/', [LandingController::class, 'update'])->name('update');
    Route::post('media', [LandingController::class, 'storeMedia'])->middleware('throttle:uploads')->name('media.store');
    Route::put('media/order', [LandingController::class, 'reorderMedia'])->name('media.reorder');
    Route::patch('media/{landingMedia}', [LandingController::class, 'updateMedia'])->name('media.update');
    Route::delete('media/{landingMedia}', [LandingController::class, 'destroyMedia'])->name('media.destroy');
});
