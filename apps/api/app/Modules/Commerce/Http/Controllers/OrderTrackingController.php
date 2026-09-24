<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Modules\Commerce\Http\Resources\OrderResource;
use App\Modules\Commerce\Models\Order;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public order tracking. The order id alone reveals nothing: the tracking token is required,
 * and a wrong token looks exactly like a missing order.
 */
final class OrderTrackingController
{
    public function show(Request $request, string $trackedOrder): OrderResource
    {
        $model = Order::query()->find($trackedOrder);

        if ($model === null || ! $model->verifyTrackingToken($request->query('token') ?? $request->header('X-Order-Token'))) {
            throw new NotFoundHttpException;
        }

        return new OrderResource($model->load(['items.modifiers', 'branch', 'table', 'history']));
    }
}
