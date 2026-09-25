<?php

use App\Modules\Analytics\Http\Controllers\ReportController;
use App\Modules\Identity\Support\PermissionCatalog as P;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'can:'.P::REPORTS_VIEW, 'feature:reports'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('summary', [ReportController::class, 'summary'])->name('summary');
    Route::get('products', [ReportController::class, 'products'])->name('products');
    Route::get('hours', [ReportController::class, 'hours'])->name('hours');
    Route::get('branches', [ReportController::class, 'branches'])->name('branches');
    Route::get('customers', [ReportController::class, 'customers'])->name('customers');
    Route::get('inventory', [ReportController::class, 'inventory'])->name('inventory');
    Route::get('export', [ReportController::class, 'export'])->middleware('throttle:report-export')->name('export');
});
