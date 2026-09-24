<?php

use App\Modules\Discounts\Http\Controllers\DiscountController;
use App\Modules\Identity\Support\PermissionCatalog as P;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'can:'.P::DISCOUNTS_MANAGE])->group(function () {
    Route::get('discounts', [DiscountController::class, 'index'])->name('discounts.index');
    Route::post('discounts', [DiscountController::class, 'store'])->name('discounts.store');
    Route::put('discounts/{discount}', [DiscountController::class, 'update'])->name('discounts.update');
    Route::delete('discounts/{discount}', [DiscountController::class, 'destroy'])->name('discounts.destroy');
});
