<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Loyalty\Http\Controllers\CustomerClubController;
use App\Modules\Loyalty\Http\Controllers\LoyaltyProgramController;
use App\Modules\Loyalty\Http\Controllers\StaffCustomerController;
use App\Modules\Loyalty\Http\Controllers\StaffWalletPaymentController;
use Illuminate\Support\Facades\Route;

// The signed-in customer's club.
Route::middleware(['tenant', 'auth:sanctum', 'actor:customer', 'throttle:storefront'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('club', [CustomerClubController::class, 'show'])->name('club.show');
    Route::get('wallet/transactions', [CustomerClubController::class, 'walletTransactions'])->name('wallet.transactions');
    Route::get('points/transactions', [CustomerClubController::class, 'pointsTransactions'])->name('points.transactions');
    Route::post('points/redeem', [CustomerClubController::class, 'redeem'])->name('points.redeem');
    Route::post('referral', [CustomerClubController::class, 'referral'])->name('referral');
    Route::post('orders/{order}/wallet-payment', [CustomerClubController::class, 'payOrder'])->name('orders.wallet-payment');
});

// Dashboard.
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member'])->group(function () {
    Route::get('customers', [StaffCustomerController::class, 'index'])->middleware('can:'.P::CUSTOMERS_VIEW)->name('customers.index');
    Route::get('customers/export', [StaffCustomerController::class, 'export'])->middleware('can:'.P::CUSTOMERS_EXPORT)->name('customers.export');
    Route::get('customers/{customer}', [StaffCustomerController::class, 'show'])->middleware('can:'.P::CUSTOMERS_VIEW)->name('customers.show');
    Route::patch('customers/{customer}', [StaffCustomerController::class, 'update'])->middleware('can:'.P::CUSTOMERS_MANAGE)->name('customers.update');
    Route::get('customers/{customer}/wallet-transactions', [StaffCustomerController::class, 'walletTransactions'])->middleware('can:'.P::CUSTOMERS_VIEW)->name('customers.wallet-transactions');
    Route::get('customers/{customer}/points-transactions', [StaffCustomerController::class, 'pointsTransactions'])->middleware('can:'.P::CUSTOMERS_VIEW)->name('customers.points-transactions');
    Route::post('customers/{customer}/wallet-adjustments', [StaffCustomerController::class, 'adjustWallet'])->middleware('can:'.P::WALLET_ADJUST)->name('customers.wallet-adjustments');
    Route::post('customers/{customer}/points-adjustments', [StaffCustomerController::class, 'adjustPoints'])->middleware('can:'.P::WALLET_ADJUST)->name('customers.points-adjustments');

    Route::post('orders/{order}/wallet-payment', [StaffWalletPaymentController::class, 'store'])->middleware('can:'.P::PAYMENTS_RECORD)->name('orders.wallet-payment');

    Route::middleware('can:'.P::LOYALTY_MANAGE)->prefix('loyalty')->name('loyalty.')->group(function () {
        Route::get('program', [LoyaltyProgramController::class, 'show'])->name('program.show');
        Route::put('program', [LoyaltyProgramController::class, 'update'])->name('program.update');
        Route::post('tiers', [LoyaltyProgramController::class, 'storeTier'])->name('tiers.store');
        Route::put('tiers/{tier}', [LoyaltyProgramController::class, 'updateTier'])->name('tiers.update');
        Route::delete('tiers/{tier}', [LoyaltyProgramController::class, 'destroyTier'])->name('tiers.destroy');
        Route::post('cashback-rules', [LoyaltyProgramController::class, 'storeRule'])->name('cashback-rules.store');
        Route::put('cashback-rules/{rule}', [LoyaltyProgramController::class, 'updateRule'])->name('cashback-rules.update');
        Route::delete('cashback-rules/{rule}', [LoyaltyProgramController::class, 'destroyRule'])->name('cashback-rules.destroy');
    });
});
