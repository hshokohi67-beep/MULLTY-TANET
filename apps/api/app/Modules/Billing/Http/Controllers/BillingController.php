<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\ManageBilling;
use App\Modules\Billing\Enums\SubscriptionState;
use App\Modules\Billing\Http\Requests\SelectionRequest;
use App\Modules\Billing\Models\Addon;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\BillingPresenter;
use App\Modules\Billing\Support\Entitlements;
use App\Modules\Billing\Support\FeatureCatalog;
use App\Modules\Billing\Support\QuoteBuilder;
use App\Modules\Billing\Support\Usage;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The café's own subscription: status, plans, quote, checkout, invoices (`billing.manage`; status: `tenant.view`). */
final class BillingController
{
    /** Minimal state for every staff member (banner, locked navigation). */
    public function status(): JsonResponse
    {
        $snap = $this->snapshot();
        $s = BillingPresenter::subscription($snap['subscription'], $snap['state'], $snap['features']);

        return response()->json(['data' => [
            'state' => $s['state'], 'state_label' => $s['state_label'], 'days_left' => $s['days_left'], 'status' => $s['status'],
            'plan' => ['key' => $s['plan']['key'], 'name' => $s['plan']['name']], 'features' => $s['features'],
        ]]);
    }

    public function show(): JsonResponse
    {
        $snap = $this->snapshot();
        $snap['subscription']->loadMissing('scheduledPlan');
        $open = BillingInvoice::query()->with('plan')->where('status', 'open')->orderByDesc('created_at')->first();

        return response()->json(['data' => [
            'subscription' => BillingPresenter::subscription($snap['subscription'], $snap['state'], $snap['features']),
            'usage' => Usage::current(),
            'open_invoice' => $open ? BillingPresenter::invoice($open) : null,
        ]]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['data' => [
            'plans' => Plan::query()->where('is_public', true)->orderBy('sort')->get()->map(fn (Plan $p) => BillingPresenter::plan($p))->values(),
            'addons' => Addon::query()->where('is_public', true)->orderBy('sort')->get()->map(fn (Addon $a) => BillingPresenter::addon($a))->values(),
            'features' => FeatureCatalog::all(),
            'vat_rate' => (int) config('billing.vat_rate', 10),
        ]]);
    }

    public function quote(SelectionRequest $request): JsonResponse
    {
        $subscription = Subscription::query()->with(['plan', 'addons.addon'])->firstOrFail();

        return response()->json(['data' => BillingPresenter::quote(
            QuoteBuilder::build($subscription, $request->plan(), (string) $request->input('cycle'), $request->addonQuantities(), CarbonImmutable::now()),
        )]);
    }

    public function checkout(SelectionRequest $request, ManageBilling $billing): JsonResponse
    {
        $result = $billing->checkout($request->plan(), (string) $request->input('cycle'), $request->addonQuantities(), (string) $request->user()?->getAuthIdentifier());

        return response()->json(['data' => [
            'mode' => $result['mode'],
            'invoice' => $result['invoice'] ? BillingPresenter::invoice($result['invoice']->load('plan')) : null,
            'redirect_url' => $result['redirect_url'],
        ]], $result['invoice'] ? 201 : 200);
    }

    public function invoices(): JsonResponse
    {
        return response()->json(['data' => BillingInvoice::query()->with('plan')->where('status', '!=', 'void')->orderByDesc('created_at')->limit(100)->get()
            ->map(fn (BillingInvoice $i) => BillingPresenter::invoice($i))->values()]);
    }

    public function invoice(BillingInvoice $billingInvoice): JsonResponse
    {
        return response()->json(['data' => BillingPresenter::invoice($billingInvoice->load('plan'))]);
    }

    /** Pays an open invoice (e.g. the renewal) through the gateway. */
    public function pay(BillingInvoice $billingInvoice, ManageBilling $billing): JsonResponse
    {
        return response()->json(['data' => ['redirect_url' => $billing->startPayment($billingInvoice)]]);
    }

    public function verify(Request $request, BillingInvoice $billingInvoice, ManageBilling $billing): JsonResponse
    {
        $authority = (string) $request->validate(['authority' => ['required', 'string', 'max:64']])['authority'];
        $paid = $billing->verify($billingInvoice, $authority);

        return response()->json(['data' => ['paid' => $paid, 'invoice' => BillingPresenter::invoice($billingInvoice->refresh()->load('plan'))]]);
    }

    public function cancel(ManageBilling $billing): JsonResponse
    {
        $billing->cancel();

        return response()->json(['data' => ['cancelled' => true]]);
    }

    public function resume(ManageBilling $billing): JsonResponse
    {
        $billing->resume();

        return response()->json(['data' => ['resumed' => true]]);
    }

    /** @return array{subscription: Subscription, state: SubscriptionState, features: array<string, bool|int|null>} */
    private function snapshot(): array
    {
        return app(Entitlements::class)->snapshot();
    }
}
