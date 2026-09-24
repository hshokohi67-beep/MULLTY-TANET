<?php

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ModifierGroupController;
use App\Modules\Catalog\Http\Controllers\PricingController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\PublicMenuController;
use App\Modules\Identity\Support\PermissionCatalog as P;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenant', 'throttle:public'])->get('public/menu', [PublicMenuController::class, 'show'])->name('public.menu');

Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member'])->prefix('catalog')->name('catalog.')->group(function () {
    $view = 'can:'.P::CATALOG_VIEW;
    $manage = 'can:'.P::CATALOG_MANAGE;

    Route::get('categories', [CategoryController::class, 'index'])->middleware($view)->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->middleware($manage)->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware($manage)->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware($manage)->name('categories.destroy');
    Route::post('categories/{category}/image', [CategoryController::class, 'uploadImage'])->middleware([$manage, 'throttle:uploads'])->name('categories.image');
    Route::delete('categories/{category}/image', [CategoryController::class, 'deleteImage'])->middleware($manage)->name('categories.image.destroy');

    Route::get('products', [ProductController::class, 'index'])->middleware($view)->name('products.index');
    Route::post('products', [ProductController::class, 'store'])->middleware(['can:'.P::CATALOG_MANAGE, 'can:'.P::PRICES_MANAGE])->name('products.store');
    Route::post('products/quick', [ProductController::class, 'quickAdd'])->middleware(['can:'.P::CATALOG_MANAGE, 'can:'.P::PRICES_MANAGE])->name('products.quick');
    Route::get('products/{product}', [ProductController::class, 'show'])->middleware($view)->name('products.show');
    Route::put('products/{product}', [ProductController::class, 'update'])->middleware($manage)->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware($manage)->name('products.destroy');
    Route::put('products/{product}/variants', [ProductController::class, 'syncVariants'])->middleware(['can:'.P::CATALOG_MANAGE, 'can:'.P::PRICES_MANAGE])->name('products.variants');
    Route::put('products/{product}/branch-prices', [PricingController::class, 'branchPrices'])->middleware('can:'.P::PRICES_MANAGE)->name('products.branch-prices');
    Route::put('products/{product}/modifier-groups', [ProductController::class, 'syncModifierGroups'])->middleware($manage)->name('products.modifier-groups');
    Route::put('products/{product}/availability', [ProductController::class, 'setAvailability'])->middleware('can:'.P::AVAILABILITY_MANAGE)->name('products.availability');
    Route::post('products/{product}/images', [ProductController::class, 'uploadImage'])->middleware([$manage, 'throttle:uploads'])->name('products.images.store');
    Route::delete('products/{product}/images/{image}', [ProductController::class, 'deleteImage'])->middleware($manage)->name('products.images.destroy');

    Route::get('modifier-groups', [ModifierGroupController::class, 'index'])->middleware($view)->name('modifier-groups.index');
    Route::post('modifier-groups', [ModifierGroupController::class, 'store'])->middleware($manage)->name('modifier-groups.store');
    Route::put('modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'update'])->middleware($manage)->name('modifier-groups.update');
    Route::delete('modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'destroy'])->middleware($manage)->name('modifier-groups.destroy');

    Route::post('prices/bulk', [PricingController::class, 'bulk'])->middleware('can:'.P::PRICES_MANAGE)->name('prices.bulk');
    Route::get('price-history', [PricingController::class, 'history'])->middleware($view)->name('prices.history');
});
