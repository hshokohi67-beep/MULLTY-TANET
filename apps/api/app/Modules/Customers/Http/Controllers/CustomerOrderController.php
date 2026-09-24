<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Commerce\Http\Resources\OrderResource;
use App\Modules\Commerce\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The signed-in customer's own order history (reorder comes with the storefront). */
final class CustomerOrderController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            Order::query()
                ->with(['items.modifiers', 'branch'])
                ->where('customer_id', $request->user('sanctum')?->getAuthIdentifier())
                ->latest('placed_at')->latest('id')
                ->cursorPaginate(20),
        );
    }
}
