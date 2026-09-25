<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Inventory\Http\Controllers\IngredientController;
use App\Modules\Inventory\Http\Controllers\PurchaseController;
use App\Modules\Inventory\Http\Controllers\RecipeController;
use App\Modules\Inventory\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member'])->group(function () {
    $view = 'can:'.P::INVENTORY_VIEW;
    $manage = 'can:'.P::INVENTORY_MANAGE;
    $purchasing = 'can:'.P::PURCHASING_MANAGE;

    Route::prefix('inventory')->name('inventory.')->group(function () use ($view, $manage, $purchasing) {
        Route::get('ingredients', [IngredientController::class, 'index'])->middleware($view)->name('ingredients.index');
        Route::post('ingredients', [IngredientController::class, 'store'])->middleware($manage)->name('ingredients.store');
        Route::put('ingredients/{ingredient}', [IngredientController::class, 'update'])->middleware($manage)->name('ingredients.update');
        Route::delete('ingredients/{ingredient}', [IngredientController::class, 'destroy'])->middleware($manage)->name('ingredients.destroy');

        Route::get('movements', [StockController::class, 'movements'])->middleware($view)->name('movements');
        Route::post('adjustments', [StockController::class, 'adjust'])->middleware($manage)->name('adjustments');
        Route::post('counts', [StockController::class, 'count'])->middleware($manage)->name('counts');

        Route::get('suppliers', [PurchaseController::class, 'suppliers'])->middleware($purchasing)->name('suppliers.index');
        Route::post('suppliers', [PurchaseController::class, 'storeSupplier'])->middleware($purchasing)->name('suppliers.store');
        Route::put('suppliers/{supplier}', [PurchaseController::class, 'updateSupplier'])->middleware($purchasing)->name('suppliers.update');

        Route::get('purchases', [PurchaseController::class, 'index'])->middleware($purchasing)->name('purchases.index');
        Route::post('purchases', [PurchaseController::class, 'store'])->middleware($purchasing)->name('purchases.store');
        Route::get('purchases/{purchaseOrder}', [PurchaseController::class, 'show'])->middleware($purchasing)->name('purchases.show');
        Route::put('purchases/{purchaseOrder}', [PurchaseController::class, 'update'])->middleware($purchasing)->name('purchases.update');
        Route::post('purchases/{purchaseOrder}/order', [PurchaseController::class, 'markOrdered'])->middleware($purchasing)->name('purchases.order');
        Route::post('purchases/{purchaseOrder}/receive', [PurchaseController::class, 'receive'])->middleware($purchasing)->name('purchases.receive');
        Route::post('purchases/{purchaseOrder}/cancel', [PurchaseController::class, 'cancel'])->middleware($purchasing)->name('purchases.cancel');
        Route::post('purchases/{purchaseOrder}/payments', [PurchaseController::class, 'pay'])->middleware($purchasing)->name('purchases.payments');
    });

    Route::get('catalog/products/{product}/recipe', [RecipeController::class, 'show'])->middleware($view)->name('recipes.show');
    Route::put('catalog/products/{product}/recipe', [RecipeController::class, 'update'])->middleware($manage)->name('recipes.update');
});
