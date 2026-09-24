<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Kitchen\Http\Controllers\KdsController;
use App\Modules\Kitchen\Http\Controllers\KdsPairingController;
use App\Modules\Kitchen\Http\Controllers\KitchenSetupController;
use App\Modules\Kitchen\Http\Middleware\RequireKitchenActor;
use Illuminate\Support\Facades\Route;

// A tablet pairs with a one-time code (tenant header, no login).
Route::middleware(['tenant', 'throttle:kds-pair'])->post('public/kds/pair', [KdsPairingController::class, 'pair'])->name('public.kds.pair');

// The kitchen screen: a paired device, or staff with kds.operate. Record ids are plain strings
// (checked against the actor's branch/station in the actions), never route-model bound.
Route::middleware(['tenant', 'auth:sanctum', RequireKitchenActor::class, 'throttle:kds'])->prefix('kds')->name('kds.')->group(function () {
    Route::get('me', [KdsController::class, 'me'])->name('me');
    Route::get('board', [KdsController::class, 'board'])->name('board');
    Route::post('items/{kitchenItem}/start', [KdsController::class, 'start'])->name('items.start');
    Route::post('items/{kitchenItem}/ready', [KdsController::class, 'ready'])->name('items.ready');
    Route::post('items/{kitchenItem}/recall', [KdsController::class, 'recall'])->name('items.recall');
    Route::post('orders/{kdsOrder}/bump', [KdsController::class, 'bump'])->name('orders.bump');
    Route::post('table-requests/{tableRequest}/acknowledge', [KdsController::class, 'acknowledge'])->name('table-requests.ack');
});

// Setup (dashboard).
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'can:'.P::KDS_MANAGE])->prefix('kitchen')->name('kitchen.')->group(function () {
    Route::get('setup', [KitchenSetupController::class, 'index'])->name('setup');
    Route::post('stations', [KitchenSetupController::class, 'storeStation'])->name('stations.store');
    Route::put('stations/{station}', [KitchenSetupController::class, 'updateStation'])->name('stations.update');
    Route::delete('stations/{station}', [KitchenSetupController::class, 'destroyStation'])->name('stations.destroy');
    Route::put('stations/{station}/products', [KitchenSetupController::class, 'syncProducts'])->name('stations.products');
    Route::post('devices', [KitchenSetupController::class, 'storeDevice'])->name('devices.store');
    Route::post('devices/{device}/repair', [KitchenSetupController::class, 'repairDevice'])->name('devices.repair');
    Route::post('devices/{device}/revoke', [KitchenSetupController::class, 'revokeDevice'])->name('devices.revoke');
});
