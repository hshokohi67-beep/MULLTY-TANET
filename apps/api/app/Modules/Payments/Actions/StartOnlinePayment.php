<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\GatewayFactory;
use App\Modules\Payments\Support\PaymentLog;
use App\Support\Localization\PersianNumber;
use App\Support\Localization\PhoneNormalizer;
use App\Support\Tenancy\StorefrontUrl;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Opens an online payment for an order waiting for payment and returns where to send the customer.
 * A recent open attempt is reused, so double clicks and retries don't open parallel sessions.
 * The gateway call happens outside the DB transaction and is always logged.
 */
final class StartOnlinePayment
{
    public function __construct(
        private readonly GatewayFactory $gateways,
        private readonly SyncOrderPaymentStatus $sync,
        private readonly PaymentLog $log,
    ) {}

    /** @return array{payment: Payment, redirect_url: string} */
    public function handle(Order $order): array
    {
        if (! $this->gateways->onlineAvailable()) {
            throw PaymentException::onlineUnavailable();
        }

        $gateway = $this->gateways->make($this->gateways->driver());
        $tenant = app(TenantContext::class)->require();

        /** @var array{payment: Payment, reused: bool} $opened */
        $opened = DB::transaction(function () use ($order, $gateway): array {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::PendingPayment || $locked->remainingDue() <= 0) {
                throw PaymentException::notAwaitingPayment();
            }

            $open = Payment::query()
                ->where('order_id', $locked->id)
                ->where('method', PaymentMethod::Online)
                ->where('status', PaymentAttemptStatus::Pending)
                ->where('created_at', '>=', now()->subMinutes((int) config('payments.attempt_reuse_minutes')))
                ->latest('created_at')
                ->first();

            if ($open !== null) {
                // The request for this attempt hasn't returned yet (a parallel click).
                return $open->authority === null ? throw PaymentException::inProgress() : ['payment' => $open, 'reused' => true];
            }

            $payment = Payment::query()->create([
                'order_id' => $locked->id,
                'method' => PaymentMethod::Online,
                'gateway' => $gateway->name(),
                'status' => PaymentAttemptStatus::Pending,
                'amount' => $locked->remainingDue(),
                'expires_at' => now()->addMinutes((int) config('payments.attempt_ttl_minutes')),
            ]);
            $this->sync->handle($locked);

            return ['payment' => $payment, 'reused' => false];
        });

        $payment = $opened['payment'];
        // Back to the host the customer ordered from (their own café subdomain when enabled).
        $callbackUrl = StorefrontUrl::to($tenant, '/s/'.$tenant->slug.'/pay/'.$payment->id);

        if ($opened['reused']) {
            return ['payment' => $payment, 'redirect_url' => $gateway->startUrl((string) $payment->authority, $callbackUrl)];
        }

        $result = $gateway->request(
            $payment->amount,
            $callbackUrl,
            sprintf('سفارش %s — %s', PersianNumber::toPersian('#'.$order->daily_number), $tenant->name),
            $order->contact_phone_e164 ? PhoneNormalizer::toLocal($order->contact_phone_e164) : null,
            $order->id,
        );
        $this->log->write($payment, 'request', $result);

        if (! $result->success || $result->authority === null || $result->redirectUrl === null) {
            // Without an authority no money can have been taken, so this attempt is simply failed.
            $this->fail($payment, $order, $result->code);
        }

        try {
            $payment->forceFill(['authority' => $result->authority])->save();
        } catch (UniqueConstraintViolationException) {
            // The gateway handed out an authority we already hold: never attach it to a second payment.
            $this->fail($payment, $order, 'duplicate_authority');
        }

        return ['payment' => $payment, 'redirect_url' => $result->redirectUrl];
    }

    private function fail(Payment $payment, Order $order, ?string $code): never
    {
        DB::transaction(function () use ($payment, $order, $code): void {
            $payment->forceFill(['status' => PaymentAttemptStatus::Failed, 'failure_code' => $code, 'failed_at' => now()])->save();
            $this->sync->handle(Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail());
        });

        throw PaymentException::gatewayRejected();
    }
}
