<?php

namespace App\Modules\Loyalty\Http\Controllers;

use App\Modules\Commerce\Models\Order;
use App\Modules\Identity\Models\User;
use App\Modules\Loyalty\Actions\PayOrderWithWallet;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Support\Localization\PersianNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Staff charge the order's customer's club wallet at the counter. */
final class StaffWalletPaymentController
{
    public function store(Request $request, Order $order, PayOrderWithWallet $pay): JsonResponse
    {
        $v = $request->validate([
            'amount' => ['nullable'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);
        $amount = isset($v['amount']) && $v['amount'] !== '' ? (int) PersianNumber::toLatin((string) $v['amount']) : null;
        /** @var User $user */
        $user = $request->user();

        $payment = $pay->handle($order, $amount !== null && $amount > 0 ? $amount : null, 'staff:'.$v['idempotency_key'], 'user', $user->id);

        return (new PaymentResource($payment))
            ->additional(['message' => __('messages.wallet_paid')])
            ->response()
            ->setStatusCode($payment->wasRecentlyCreated ? 201 : 200);
    }
}
