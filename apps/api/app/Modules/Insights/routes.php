<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Insights\Http\Controllers\DashboardController;
use App\Modules\Insights\Http\Controllers\OverviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'can:'.P::TENANT_VIEW])->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('overview', [OverviewController::class, 'show'])->name('overview');
    Route::get('layout', [DashboardController::class, 'layout'])->name('layout.show');
    Route::put('layout', [DashboardController::class, 'saveLayout'])->name('layout.update');
    Route::delete('layout', [DashboardController::class, 'resetLayout'])->name('layout.reset');
    Route::get('widgets/{widget}', [DashboardController::class, 'widget'])->name('widgets.show');
    Route::post('shift-notes', [DashboardController::class, 'storeNote'])->name('shift-notes.store');
    Route::delete('shift-notes/{shiftNote}', [DashboardController::class, 'destroyNote'])->name('shift-notes.destroy');
});
