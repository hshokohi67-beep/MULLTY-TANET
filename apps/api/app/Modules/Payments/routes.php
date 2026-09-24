<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Payments\Http\Controllers\PaymentController;
use App\Modules\Payments\Http\Controllers\StorefrontPaymentController;
use Illuminate\Support\Facades\Route;

// Storefront. The order is proven by X-Order-Token, the payment by the gateway authority.
Route::middleware(['tenant', 'throttle:checkout'])->prefix('public')->name('public.')->group(function () {
    Route::post('orders/{trackedOrder}/pay', [StorefrontPaymentController::class, 'start'])->name('orders.pay');
    Route::post('payments/{paymentId}/verify', [StorefrontPaymentController::class, 'verify'])->name('payments.verify');
});

// Dashboard.
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member'])->group(function () {
    Route::get('payments', [PaymentController::class, 'index'])->middleware('can:'.P::PAYMENTS_VIEW)->name('payments.index');
    Route::get('payments/summary', [PaymentController::class, 'summary'])->middleware('can:'.P::PAYMENTS_VIEW)->name('payments.summary');
    Route::get('orders/{order}/payments', [PaymentController::class, 'forOrder'])->middleware('can:'.P::PAYMENTS_VIEW)->name('orders.payments.index');
    Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->middleware('can:'.P::PAYMENTS_RECORD)->name('orders.payments.store');
    Route::post('payments/{payment}/refunds', [PaymentController::class, 'refund'])->middleware('can:'.P::PAYMENTS_REFUND)->name('payments.refunds.store');
});
