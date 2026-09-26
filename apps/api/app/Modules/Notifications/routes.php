<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Notifications\Http\Controllers\PublicPushController;
use App\Modules\Notifications\Http\Controllers\StaffPushController;
use Illuminate\Support\Facades\Route;

// The public VAPID key (the same for every café).
Route::get('public/push/key', [PublicPushController::class, 'key'])->middleware('throttle:public')->name('public.push.key');

// A customer following their order (tracking token in X-Order-Token).
Route::middleware(['tenant', 'throttle:push-subscribe'])->post('public/orders/{trackedOrder}/push', [PublicPushController::class, 'followOrder'])->name('public.orders.push');

// A staff member's device, for new orders.
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'can:'.P::ORDERS_VIEW, 'throttle:push-subscribe'])->prefix('push/subscription')->name('push.')->group(function () {
    Route::post('/', [StaffPushController::class, 'subscribe'])->name('subscribe');
    Route::post('status', [StaffPushController::class, 'status'])->name('status');
    Route::post('remove', [StaffPushController::class, 'unsubscribe'])->name('unsubscribe');
});
