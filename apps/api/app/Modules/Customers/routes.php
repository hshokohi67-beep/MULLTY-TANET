<?php

use App\Modules\Customers\Http\Controllers\CustomerAddressController;
use App\Modules\Customers\Http\Controllers\CustomerOrderController;
use App\Modules\Customers\Http\Controllers\CustomerProfileController;
use Illuminate\Support\Facades\Route;

// The signed-in customer's own data, inside one tenant.
Route::middleware(['tenant', 'auth:sanctum', 'actor:customer', 'throttle:storefront'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('addresses', [CustomerAddressController::class, 'index'])->name('addresses.index');
    Route::post('addresses', [CustomerAddressController::class, 'store'])->name('addresses.store');
    Route::put('addresses/{address}', [CustomerAddressController::class, 'update'])->name('addresses.update');
    Route::delete('addresses/{address}', [CustomerAddressController::class, 'destroy'])->name('addresses.destroy');
    Route::get('orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('profile', [CustomerProfileController::class, 'show'])->name('profile.show');
    Route::patch('profile', [CustomerProfileController::class, 'update'])->name('profile.update');
});
