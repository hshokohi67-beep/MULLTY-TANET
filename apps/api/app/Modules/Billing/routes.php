<?php

use App\Modules\Billing\Http\Controllers\BillingController;
use App\Modules\Billing\Http\Controllers\PlatformBillingController;
use App\Modules\Identity\Support\PermissionCatalog as P;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member'])->prefix('billing')->name('billing.')->group(function () {
    Route::get('status', [BillingController::class, 'status'])->middleware('can:'.P::TENANT_VIEW)->name('status');

    Route::middleware('can:'.P::BILLING_MANAGE)->group(function () {
        Route::get('/', [BillingController::class, 'show'])->name('show');
        Route::get('plans', [BillingController::class, 'plans'])->name('plans');
        Route::post('quote', [BillingController::class, 'quote'])->name('quote');
        Route::post('checkout', [BillingController::class, 'checkout'])->middleware('throttle:billing-checkout')->name('checkout');
        Route::get('invoices', [BillingController::class, 'invoices'])->name('invoices.index');
        Route::get('invoices/{billingInvoice}', [BillingController::class, 'invoice'])->name('invoices.show');
        Route::post('invoices/{billingInvoice}/pay', [BillingController::class, 'pay'])->middleware('throttle:billing-checkout')->name('invoices.pay');
        Route::post('invoices/{billingInvoice}/verify', [BillingController::class, 'verify'])->name('invoices.verify');
        Route::post('cancel', [BillingController::class, 'cancel'])->name('cancel');
        Route::post('resume', [BillingController::class, 'resume'])->name('resume');
    });
});

Route::middleware(['auth:sanctum', 'actor:platform'])->prefix('platform')->name('platform.billing.')->group(function () {
    Route::get('subscriptions', [PlatformBillingController::class, 'subscriptions'])->name('subscriptions');
    Route::get('plans', [PlatformBillingController::class, 'plans'])->name('plans');
    Route::put('plans/{plan}', [PlatformBillingController::class, 'updatePlan'])->name('plans.update');
    Route::get('tenants/{tenant}/entitlements', [PlatformBillingController::class, 'entitlements'])->name('entitlements');
    Route::post('tenants/{tenant}/extend', [PlatformBillingController::class, 'extend'])->name('extend');
    Route::post('tenants/{tenant}/overrides', [PlatformBillingController::class, 'override'])->name('overrides.store');
    Route::delete('tenants/{tenant}/overrides/{feature}', [PlatformBillingController::class, 'removeOverride'])->name('overrides.destroy');
    Route::post('tenants/{tenant}/invoices/{invoiceId}/mark-paid', [PlatformBillingController::class, 'markPaid'])->name('invoices.mark-paid');
});
