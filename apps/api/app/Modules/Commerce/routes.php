<?php

use App\Modules\Commerce\Http\Controllers\CheckoutController;
use App\Modules\Commerce\Http\Controllers\DeliveryZoneController;
use App\Modules\Commerce\Http\Controllers\OrderController;
use App\Modules\Commerce\Http\Controllers\OrderTrackingController;
use App\Modules\Commerce\Http\Controllers\PublicStorefrontController;
use App\Modules\Commerce\Http\Controllers\StorefrontCartController;
use App\Modules\Commerce\Http\Controllers\StorefrontTableController;
use App\Modules\Commerce\Http\Controllers\TableController;
use App\Modules\Identity\Support\PermissionCatalog as P;
use Illuminate\Support\Facades\Route;

// Storefront (guest or customer). Tokens travel in headers: X-Table-Session, X-Cart-Token, Idempotency-Key.
Route::middleware(['tenant'])->prefix('public')->name('public.')->group(function () {
    Route::post('tables/session', [StorefrontTableController::class, 'join'])->middleware('throttle:storefront')->name('tables.session');
    Route::post('tables/requests', [StorefrontTableController::class, 'request'])->middleware('throttle:table-requests')->name('tables.requests');

    Route::get('storefront', [PublicStorefrontController::class, 'show'])->middleware('throttle:public')->name('storefront');
    Route::get('preorder-slots', [PublicStorefrontController::class, 'preorderSlots'])->middleware('throttle:storefront')->name('preorder-slots');
    Route::post('delivery/check', [PublicStorefrontController::class, 'deliveryCheck'])->middleware('throttle:delivery-check')->name('delivery.check');

    Route::middleware('throttle:storefront')->group(function () {
        Route::post('carts', [StorefrontCartController::class, 'store'])->name('carts.store');
        Route::get('cart', [StorefrontCartController::class, 'show'])->name('cart.show');
        Route::post('cart/items', [StorefrontCartController::class, 'addItem'])->name('cart.items.store');
        Route::patch('cart/items/{item}', [StorefrontCartController::class, 'updateItem'])->name('cart.items.update');
        Route::delete('cart/items/{item}', [StorefrontCartController::class, 'removeItem'])->name('cart.items.destroy');
        Route::post('cart/reorder', [StorefrontCartController::class, 'reorder'])->name('cart.reorder');
        Route::get('orders/{trackedOrder}', [OrderTrackingController::class, 'show'])->name('orders.track');
    });

    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout');
});

// Dashboard.
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member'])->group(function () {
    Route::get('tables', [TableController::class, 'index'])->middleware('can:'.P::ORDERS_VIEW)->name('tables.index');
    Route::post('tables', [TableController::class, 'store'])->middleware('can:'.P::TABLES_MANAGE)->name('tables.store');
    Route::put('tables/{table}', [TableController::class, 'update'])->middleware('can:'.P::TABLES_MANAGE)->name('tables.update');
    Route::post('tables/{table}/qr', [TableController::class, 'issueQr'])->middleware('can:'.P::TABLES_MANAGE)->name('tables.qr');
    Route::post('tables/{table}/close-session', [TableController::class, 'closeSession'])->middleware('can:'.P::ORDERS_MANAGE)->name('tables.close-session');
    Route::get('table-requests', [TableController::class, 'requests'])->middleware('can:'.P::ORDERS_VIEW)->name('table-requests.index');
    Route::post('table-requests/{tableRequest}/acknowledge', [TableController::class, 'acknowledge'])->middleware('can:'.P::ORDERS_MANAGE)->name('table-requests.ack');

    Route::get('delivery-zones', [DeliveryZoneController::class, 'index'])->middleware('can:'.P::DELIVERY_MANAGE)->name('delivery-zones.index');
    Route::post('delivery-zones', [DeliveryZoneController::class, 'store'])->middleware('can:'.P::DELIVERY_MANAGE)->name('delivery-zones.store');
    Route::post('delivery-zones/check', [DeliveryZoneController::class, 'check'])->middleware('can:'.P::DELIVERY_MANAGE)->name('delivery-zones.check');
    Route::put('delivery-zones/{zone}', [DeliveryZoneController::class, 'update'])->middleware('can:'.P::DELIVERY_MANAGE)->name('delivery-zones.update');
    Route::delete('delivery-zones/{zone}', [DeliveryZoneController::class, 'destroy'])->middleware('can:'.P::DELIVERY_MANAGE)->name('delivery-zones.destroy');

    Route::get('orders', [OrderController::class, 'index'])->middleware('can:'.P::ORDERS_VIEW)->name('orders.index');
    Route::get('orders/summary', [OrderController::class, 'summary'])->middleware('can:'.P::ORDERS_VIEW)->name('orders.summary');
    Route::get('orders/live-version', [OrderController::class, 'liveVersion'])->middleware('can:'.P::ORDERS_VIEW)->name('orders.live-version');
    Route::post('orders', [OrderController::class, 'store'])->middleware('can:'.P::ORDERS_CREATE)->name('orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->middleware('can:'.P::ORDERS_VIEW)->name('orders.show');
    Route::post('orders/{order}/status', [OrderController::class, 'transition'])->middleware('can:'.P::ORDERS_MANAGE)->name('orders.status');
});
