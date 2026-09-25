<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\BillingPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\BillingGateway;
use App\Modules\Billing\Support\Entitlements;
use App\Modules\Billing\Support\InvoiceNumbers;
use App\Modules\Billing\Support\QuoteBuilder;
use App\Modules\Payments\Support\Gateways\GatewayResult;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Checkout, gateway payment and verification for subscription invoices. Gateway calls always
 * happen outside database transactions and every request/response is kept on the payment row.
 */
final class ManageBilling
{
    public function __construct(
        private readonly BillingGateway $gateways,
        private readonly ApplyPaidInvoice $apply,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, int>  $addons  addon id => quantity
     * @return array{mode: string, invoice: ?BillingInvoice, redirect_url: ?string}
     */
    public function checkout(Plan $plan, string $cycle, array $addons, string $userId): array
    {
        if (! $plan->is_public) {
            throw BillingException::planUnavailable();
        }
        $subscription = Subscription::query()->with(['plan', 'addons.addon'])->firstOrFail();
        $quote = QuoteBuilder::build($subscription, $plan, $cycle, $addons, CarbonImmutable::now());

        if ($quote['mode'] === 'scheduled') {
            $subscription->update(['scheduled_plan_id' => $plan->id, 'scheduled_cycle' => $cycle, 'scheduled_addons' => $quote['addons']]);
            $this->audit->record('billing.change_scheduled', $subscription, ['plan' => $plan->key, 'cycle' => $cycle]);

            return ['mode' => 'scheduled', 'invoice' => null, 'redirect_url' => null];
        }

        $invoice = DB::transaction(function () use ($quote, $userId): BillingInvoice {
            // One open checkout at a time: an abandoned one is replaced.
            BillingInvoice::query()->where('status', 'open')->where('kind', 'checkout')->update(['status' => 'void']);

            return $this->createInvoice($quote, 'checkout', $userId, now()->addDay());
        });
        $this->audit->record('billing.invoice_created', $invoice, ['number' => $invoice->number, 'total' => $invoice->total]);

        if ($invoice->total === 0) {
            $this->markPaid($invoice, 'credit', null);

            return ['mode' => $quote['mode'], 'invoice' => $invoice->refresh(), 'redirect_url' => null];
        }

        return ['mode' => $quote['mode'], 'invoice' => $invoice, 'redirect_url' => $this->startPayment($invoice)];
    }

    /**
     * @param  array{mode: string, plan: Plan, cycle: string, addons: list<array{addon_id: string, quantity: int}>, lines: list<array{label: string, amount: int}>, subtotal: int, credit: int, vat_rate: int, vat: int, total: int, period_start: ?CarbonImmutable, period_end: ?CarbonImmutable, warnings: list<string>}  $quote
     */
    public function createInvoice(array $quote, string $kind, ?string $userId, CarbonImmutable|Carbon $dueAt): BillingInvoice
    {
        return BillingInvoice::query()->create([
            'number' => InvoiceNumbers::next(),
            'kind' => $kind,
            'status' => 'open',
            'plan_id' => $quote['plan']->id,
            'cycle' => $quote['cycle'],
            'addons' => $quote['addons'],
            'mode' => $quote['mode'],
            'lines' => $quote['lines'],
            'subtotal' => $quote['subtotal'],
            'credit' => $quote['credit'],
            'vat_rate' => $quote['vat_rate'],
            'vat' => $quote['vat'],
            'total' => $quote['total'],
            'period_start' => $quote['period_start'],
            'period_end' => $quote['period_end'],
            'due_at' => $dueAt,
            'created_by' => $userId,
        ]);
    }

    /** Opens a gateway session for an open invoice and returns where to send the owner. */
    public function startPayment(BillingInvoice $invoice): string
    {
        if ($invoice->status !== 'open' || $invoice->total <= 0) {
            throw BillingException::invoiceNotPayable();
        }
        $gateway = $this->gateways->make();
        $tenant = app(TenantContext::class)->require();
        $callback = config('payments.storefront_url').'/billing/return?invoice='.$invoice->id;

        $result = $gateway->request($invoice->total, $callback, "اشتراک {$tenant->name} • صورت‌حساب {$invoice->number}");
        $payment = BillingPayment::query()->create([
            'invoice_id' => $invoice->id,
            'gateway' => $gateway->name(),
            'status' => $result->success ? 'pending' : 'failed',
            'amount' => $invoice->total,
            'authority' => $result->authority,
            'failure_code' => $result->success ? null : $result->code,
            'log' => [self::entry('request', $result)],
        ]);

        if (! $result->success || $result->redirectUrl === null) {
            Log::warning('billing.gateway_request_failed', ['payment' => $payment->id, 'code' => $result->code]);

            throw BillingException::gatewayFailed();
        }

        return $result->redirectUrl;
    }

    /**
     * Confirms a gateway payment. Safe to call repeatedly (browser return, refresh): once the
     * invoice is paid, later calls just report it.
     */
    public function verify(BillingInvoice $invoice, string $authority): bool
    {
        $payment = BillingPayment::query()->where('invoice_id', $invoice->id)->where('authority', $authority)->first()
            ?? throw BillingException::invoiceNotPayable();

        if ($payment->status === 'paid' || $invoice->status === 'paid') {
            return $invoice->status === 'paid';
        }
        if ($invoice->status !== 'open') {
            return false;
        }

        $result = $this->gateways->make($payment->gateway)->verify($payment->amount, $authority);
        $payment->update(['log' => [...($payment->log ?? []), self::entry('verify', $result)]]);

        if (! $result->success) {
            $payment->update(['status' => 'failed', 'failure_code' => $result->code]);

            return false;
        }

        $payment->update(['status' => 'paid', 'ref_id' => $result->refId, 'card_pan' => $result->cardPan, 'paid_at' => now()]);
        $this->markPaid($invoice, 'gateway', $result->refId);

        return true;
    }

    /** Marks an open invoice paid and applies it, exactly once (row lock on the invoice). */
    public function markPaid(BillingInvoice $invoice, string $via, ?string $reference): void
    {
        DB::transaction(function () use ($invoice, $via, $reference): void {
            $locked = BillingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== 'open') {
                return;
            }
            $locked->update(['status' => 'paid', 'paid_at' => now(), 'paid_via' => $via, 'reference' => $reference]);
            $this->apply->handle($locked);
            $this->audit->record('billing.invoice_paid', $locked, ['number' => $locked->number, 'total' => $locked->total, 'via' => $via]);
        });
        $invoice->refresh();
    }

    public function cancel(): Subscription
    {
        $subscription = Subscription::query()->firstOrFail();
        if ($subscription->status !== 'active' || $subscription->current_period_end === null || $subscription->current_period_end->isPast()) {
            throw BillingException::notCancellable();
        }
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now(), 'scheduled_plan_id' => null, 'scheduled_cycle' => null, 'scheduled_addons' => null]);
        $this->forget();
        $this->audit->record('billing.cancelled', $subscription, []);

        return $subscription;
    }

    public function resume(): Subscription
    {
        $subscription = Subscription::query()->firstOrFail();
        if ($subscription->status !== 'cancelled' || $subscription->current_period_end === null || $subscription->current_period_end->isPast()) {
            throw BillingException::notResumable();
        }
        $subscription->update(['status' => 'active', 'cancelled_at' => null]);
        $this->forget();
        $this->audit->record('billing.resumed', $subscription, []);

        return $subscription;
    }

    private function forget(): void
    {
        app(Entitlements::class)->forget();
    }

    /** @return array<string, mixed> */
    private static function entry(string $action, GatewayResult $result): array
    {
        return ['action' => $action, 'at' => now()->toIso8601String(), 'success' => $result->success, 'code' => $result->code, 'request' => $result->request, 'response' => $result->response];
    }
}
