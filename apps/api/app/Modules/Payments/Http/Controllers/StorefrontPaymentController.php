<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Commerce\Models\Order;
use App\Modules\Payments\Actions\StartOnlinePayment;
use App\Modules\Payments\Actions\VerifyOnlinePayment;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\FailureMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Customer side of online payment: start (needs the order's tracking token) and verify
 * (needs the gateway authority from the return URL, which only the payer has).
 */
final class StorefrontPaymentController
{
    public function start(Request $request, string $trackedOrder, StartOnlinePayment $start): JsonResponse
    {
        $order = Order::query()->find($trackedOrder);

        if ($order === null || ! $order->verifyTrackingToken($request->header('X-Order-Token'))) {
            throw new NotFoundHttpException;
        }

        ['payment' => $payment, 'redirect_url' => $url] = $start->handle($order);

        return response()->json(['data' => ['payment_id' => $payment->id, 'amount' => $payment->amount, 'redirect_url' => $url]]);
    }

    public function verify(Request $request, string $paymentId, VerifyOnlinePayment $verify): JsonResponse
    {
        $authority = $request->validate(['authority' => ['required', 'string', 'max:64']])['authority'];
        $payment = Payment::query()->find($paymentId) ?? throw PaymentException::notFound();
        $payment = $verify->handle($payment, $authority);
        $order = Order::query()->with('branch')->findOrFail($payment->order_id);

        return response()->json(['data' => [
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'amount' => $payment->amount,
            'ref_id' => $payment->ref_id,
            'card_pan' => $payment->card_pan,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'failure_message' => FailureMessage::for($payment->failure_code),
            'order' => [
                'id' => $order->id,
                'daily_number' => $order->daily_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'total' => $order->total,
                'remaining_due' => $order->remainingDue(),
                'branch' => $order->branch->name,
                // The payer proved possession of the authority, so they may keep tracking the order.
                'tracking_token' => $order->trackingToken(),
            ],
        ]]);
    }
}
