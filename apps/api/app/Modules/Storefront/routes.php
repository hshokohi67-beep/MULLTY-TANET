<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Storefront\Http\Controllers\PublicStoryController;
use App\Modules\Storefront\Http\Controllers\StoryController;
use Illuminate\Support\Facades\Route;

// Storefront (guests): live stories and anonymous view/click counting.
Route::middleware(['tenant'])->prefix('public')->name('public.')->group(function () {
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
