<?php

namespace App\Modules\Loyalty\Http\Controllers;

use App\Modules\Commerce\Models\Order;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Actions\PayOrderWithWallet;
use App\Modules\Loyalty\Actions\RedeemPoints;
use App\Modules\Loyalty\Actions\Referrals;
use App\Modules\Loyalty\Http\Resources\LedgerResource;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Loyalty\Models\Wallet;
use App\Modules\Loyalty\Models\WalletTransaction;
use App\Modules\Loyalty\Support\ClubSummary;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Support\Localization\PersianNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The signed-in customer's own club: balances, history, redeem, referral, paying with the wallet. */
final class CustomerClubController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => ClubSummary::for($this->customer($request))]);
    }

    public function walletTransactions(Request $request): AnonymousResourceCollection
    {
        $wallet = Wallet::query()->where('customer_id', $this->customer($request)->id)->value('id');

        return LedgerResource::collection(WalletTransaction::query()->where('wallet_id', $wallet)->latest('created_at')->latest('id')->cursorPaginate(30));
    }

    public function pointsTransactions(Request $request): AnonymousResourceCollection
    {
        $account = LoyaltyAccount::query()->where('customer_id', $this->customer($request)->id)->value('id');

        return LedgerResource::collection(LoyaltyTransaction::query()->where('account_id', $account)->latest('created_at')->latest('id')->cursorPaginate(30));
    }

    public function redeem(Request $request, RedeemPoints $redeem): JsonResponse
    {
        $points = (int) PersianNumber::toLatin((string) $request->validate(['points' => ['required']])['points']);
        $customer = $this->customer($request);
        $redeem->handle($customer, $points);

        return response()->json(['data' => ClubSummary::for($customer), 'message' => __('messages.points_redeemed')]);
    }

    public function referral(Request $request, Referrals $referrals): JsonResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:12']])['code'];
        $referrals->apply($this->customer($request), PersianNumber::toLatin($code));

        return response()->json(['message' => __('messages.referral_applied')]);
    }

    public function payOrder(Request $request, Order $order, PayOrderWithWallet $pay): JsonResponse
    {
        $customer = $this->customer($request);

        // Someone else's order looks exactly like a missing one.
        if ($order->customer_id !== $customer->id) {
            throw new NotFoundHttpException;
        }

        $payment = $pay->handle($order, idempotencyKey: $request->header('Idempotency-Key') ? 'customer:'.$customer->id.':'.$request->header('Idempotency-Key') : null, actorType: 'customer', actorId: $customer->id);
        $order->refresh();

        return response()->json([
            'data' => (new PaymentResource($payment))->resolve(),
            'order' => ['status' => $order->status->value, 'payment_status' => $order->payment_status->value, 'remaining_due' => $order->remainingDue()],
            'message' => __('messages.wallet_paid'),
        ]);
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user('sanctum');

        return $customer;
    }
}
