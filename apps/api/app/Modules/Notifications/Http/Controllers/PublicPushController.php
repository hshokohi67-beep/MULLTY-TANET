<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Commerce\Models\Order;
use App\Modules\Notifications\Actions\ManagePushSubscriptions;
use App\Modules\Notifications\Http\Requests\PushSubscriptionRequest;
use App\Support\Push\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The platform's public VAPID key, and a customer following one order (tracking token required). */
final class PublicPushController
{
    public function key(): JsonResponse
    {
        return response()->json(['data' => ['public_key' => WebPushSender::publicKey()]])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function followOrder(PushSubscriptionRequest $request, string $trackedOrder, ManagePushSubscriptions $subscriptions): Response
    {
        $order = Order::query()->find($trackedOrder);
        // Same rule as tracking: a wrong token looks exactly like a missing order.
        if ($order === null || ! $order->verifyTrackingToken($request->header('X-Order-Token'))) {
            throw new NotFoundHttpException;
        }
        $url = $request->validated('url');
        $subscriptions->forOrder($order, $request->keys(), is_string($url) ? $url : null);

        return response()->noContent();
    }
}
