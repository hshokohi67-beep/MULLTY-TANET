<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Occasions\Http\Controllers\OccasionController;
use App\Modules\Occasions\Http\Controllers\PlatformOccasionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'can:'.P::OCCASIONS_VIEW])->prefix('occasions')->name('occasions.')->group(function () {
    Route::get('upcoming', [OccasionController::class, 'upcoming'])->name('upcoming');
    Route::put('{occasionDate}/state', [OccasionController::class, 'state'])->name('state');
});

Route::middleware(['auth:sanctum', 'actor:platform'])->prefix('platform')->name('platform.occasions.')->group(function () {
    Route::get('occasions', [PlatformOccasionController::class, 'index'])->name('index');
    Route::post('occasions', [PlatformOccasionController::class, 'store'])->name('store');
    Route::put('occasions/{occasion}', [PlatformOccasionController::class, 'update'])->name('update');
    Route::put('occasion-dates/{occasionDate}', [PlatformOccasionController::class, 'confirmDate'])->name('dates.confirm');
});
