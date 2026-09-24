<?php

use App\Modules\Identity\Http\Controllers\CustomerAuthController;
use App\Modules\Identity\Http\Controllers\StaffAuthController;
use App\Modules\Identity\Http\Controllers\TeamController;
use App\Modules\Identity\Support\PermissionCatalog as P;
use Illuminate\Support\Facades\Route;

// Staff (dashboard) authentication: not tenant-bound, a user may belong to several tenants.
Route::post('auth/staff/login', [StaffAuthController::class, 'login'])->middleware('throttle:staff-login')->name('staff.login');

Route::middleware(['auth:sanctum', 'actor:staff'])->group(function () {
    Route::post('auth/staff/logout', [StaffAuthController::class, 'logout'])->name('staff.logout');
    Route::get('auth/staff/me', [StaffAuthController::class, 'me'])->name('staff.me');
});

// Customer authentication: always inside one tenant.
Route::middleware('tenant')->prefix('auth/customer')->name('customer.')->group(function () {
    Route::post('otp/request', [CustomerAuthController::class, 'requestOtp'])->middleware('throttle:otp-request')->name('otp.request');
    Route::post('otp/verify', [CustomerAuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify')->name('otp.verify');

    Route::middleware(['auth:sanctum', 'actor:customer'])->group(function () {
        Route::post('logout', [CustomerAuthController::class, 'logout'])->name('logout');
        Route::get('me', [CustomerAuthController::class, 'me'])->name('me');
    });
});

// Tenant dashboard: team & access control.
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member'])->group(function () {
    Route::get('team', [TeamController::class, 'index'])->middleware('can:'.P::TEAM_VIEW)->name('team.index');
    Route::post('team', [TeamController::class, 'store'])->middleware('can:'.P::TEAM_MANAGE)->name('team.store');
    Route::put('team/{member}/roles', [TeamController::class, 'updateRoles'])->middleware('can:'.P::TEAM_MANAGE)->name('team.roles');
    Route::get('roles', [TeamController::class, 'roles'])->middleware('can:'.P::TEAM_VIEW)->name('roles.index');
    Route::get('permissions', [TeamController::class, 'permissions'])->middleware('can:'.P::TEAM_VIEW)->name('permissions.index');
});
