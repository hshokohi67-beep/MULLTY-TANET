<?php

use App\Modules\Core\Http\Controllers\AuditLogController;
use App\Modules\Core\Http\Controllers\BranchController;
use App\Modules\Core\Http\Controllers\BrandingController;
use App\Modules\Core\Http\Controllers\Platform\PlatformTenantController;
use App\Modules\Core\Http\Controllers\PublicTenantController;
use App\Modules\Core\Http\Controllers\SettingsController;
use App\Modules\Core\Http\Controllers\TenantController;
use App\Modules\Identity\Support\PermissionCatalog as P;
use Illuminate\Support\Facades\Route;

// Public storefront data (explicit public projection only).
Route::middleware(['tenant', 'throttle:public'])->get('public/tenant', [PublicTenantController::class, 'show'])->name('public.tenant');

// Tenant dashboard.
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member'])->group(function () {
    Route::get('tenant', [TenantController::class, 'show'])->middleware('can:'.P::TENANT_VIEW)->name('tenant.show');
    Route::patch('tenant', [TenantController::class, 'update'])->middleware('can:'.P::TENANT_UPDATE)->name('tenant.update');

    Route::get('tenant/branding', [BrandingController::class, 'show'])->middleware('can:'.P::TENANT_VIEW)->name('branding.show');
    Route::patch('tenant/branding', [BrandingController::class, 'update'])->middleware('can:'.P::BRANDING_UPDATE)->name('branding.update');
    Route::post('tenant/branding/logo', [BrandingController::class, 'uploadLogo'])->middleware(['can:'.P::BRANDING_UPDATE, 'throttle:uploads'])->name('branding.logo');
    Route::post('tenant/branding/cover', [BrandingController::class, 'uploadCover'])->middleware(['can:'.P::BRANDING_UPDATE, 'throttle:uploads'])->name('branding.cover');
    Route::delete('tenant/branding/cover', [BrandingController::class, 'deleteCover'])->middleware('can:'.P::BRANDING_UPDATE)->name('branding.cover.destroy');

    Route::get('tenant/settings', [SettingsController::class, 'show'])->middleware('can:'.P::SETTINGS_VIEW)->name('settings.show');
    Route::patch('tenant/settings', [SettingsController::class, 'update'])->middleware('can:'.P::SETTINGS_UPDATE)->name('settings.update');

    Route::get('branches', [BranchController::class, 'index'])->middleware('can:'.P::BRANCHES_VIEW)->name('branches.index');
    Route::post('branches', [BranchController::class, 'store'])->middleware('can:'.P::BRANCHES_MANAGE)->name('branches.store');
    Route::get('branches/{branch}', [BranchController::class, 'show'])->middleware('can:'.P::BRANCHES_VIEW)->name('branches.show');
    Route::put('branches/{branch}', [BranchController::class, 'update'])->middleware('can:'.P::BRANCHES_MANAGE)->name('branches.update');
    Route::put('branches/{branch}/opening-hours', [BranchController::class, 'updateOpeningHours'])->middleware('can:'.P::BRANCHES_MANAGE)->name('branches.hours');
    Route::get('branches/{branch}/open-status', [BranchController::class, 'openStatus'])->middleware('can:'.P::BRANCHES_VIEW)->name('branches.status');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('can:'.P::AUDIT_VIEW)->name('audit.index');
});

// Platform (super admin).
Route::middleware(['auth:sanctum', 'actor:platform'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('tenants', [PlatformTenantController::class, 'index'])->name('tenants.index');
    Route::post('tenants', [PlatformTenantController::class, 'store'])->name('tenants.store');
});
