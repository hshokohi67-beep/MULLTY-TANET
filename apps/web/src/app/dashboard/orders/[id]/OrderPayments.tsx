'use client';

import { useActionState, useState } from 'react';
import { Alert, Badge, Button, Card, CardHeader, Ltr, SelectField, TextField } from '@cafe/ui';
import { formatJalaliDateTime, formatMoney } from '@cafe/locale';
import { payOrderWithWallet } from '@/app/actions/club';
import { recordPayment, recordRefund } from '@/app/actions/payments';
import { FormStatus } from '@/components/FormStatus';
import { MoneyField } from '@/components/MoneyField';
import { useSubmissionKey } from '@/components/useSubmissionKey';
import { rialToTomanInput } from '@/lib/money';
import type { FormState, Order, Payment } from '@/lib/types';

const STATUS_TONE = { paid: 'success', pending: 'info', failed: 'danger', expired: 'neutral' } as const;

function RecordPaymentForm({ order }: { order: Order }) {
  const [key, renew] = useSubmissionKey();
  const [state, action, pending] = useActionState<FormState, FormData>(async (prev, formData) => {
    const result = await recordPayment(order.id, prev, formData);
    if (result.ok) renew();

    return result;
  }, { ok: false });
  const e = state.errors ?? {};

  return (
    <form key={key} action={action} className="flex flex-col gap-3 border-t border-border p-5">
      <p className="text-sm font-semibold">ثبت پرداخت در صندوق</p>
      <FormStatus state={state} />
      <input type="hidden" name="idempotency_key" value={key} />
      <div className="grid gap-3 sm:grid-cols-2">
        <SelectField label="روش" name="method" defaultValue="cash" error={e.method}>
          <option value="cash">نقدی</option>
          <option value="card_pos">کارتخوان</option>
          <option value="other">سایر</option>
        </SelectField>
        <MoneyField label="مبلغ (تومان)" name="amount" defaultValue={rialToTomanInput(order.remaining_due)} error={e.amount} />
        <TextField label="شماره‌ی پیگیری" name="reference" ltr error={e.reference} hint="اختیاری؛ مثلاً شماره‌ی رسید کارتخوان" />
        <TextField label="یادداشت" name="note" error={e.note} />
      </div>
      <div><Button type="submit" size="sm" loading={pending}>ثبت پرداخت</Button></div>
    </form>
  );
}

function RefundForm({ payment, onClose }: { payment: Payment; onClose: () => void }) {
  const [key, renew] = useSubmissionKey();
  const [state, action, pending] = useActionState<FormState, FormData>(async (prev, formData) => {
    const result = await recordRefund(payment.id, prev, formData);
    if (result.ok) renew();

    return result;
  }, { ok: false });
  const e = state.errors ?? {};

  return (
    <form key={key} action={action} className="mt-2 flex flex-col gap-3 rounded-md border border-border p-3">
      <FormStatus state={state} />
      <input type="hidden" name="idempotency_key" value={key} />
      {payment.method === 'online' ? (
        <p className="text-xs text-text-muted">بازگشت وجه پرداخت اینترنتی را ابتدا در پنل زرین‌پال انجام دهید، سپس اینجا ثبت کنید.</p>
      ) : payment.method === 'wallet' ? (
        <p className="text-xs text-text-muted">مبلغ به کیف پول مشتری برمی‌گردد.</p>
      ) : null}
      <div className="grid gap-3 sm:grid-cols-2">
        <MoneyField label="مبلغ بازگشتی (تومان)" name="amount" defaultValue={rialToTomanInput(payment.refundable)} required error={e.amount} />
        {payment.method === 'wallet' ? (
          <input type="hidden" name="method" value="wallet" />
        ) : (
          <SelectField label="روش بازگشت" name="method" defaultValue={payment.method === 'online' ? 'gateway_panel' : 'cash'} error={e.method}>
            <option value="gateway_panel">از پنل درگاه</option>
            <option value="cash">نقدی</option>
            <option value="card">کارت‌به‌کارت</option>
            <option value="other">سایر</option>
          </SelectField>
        )}
        <TextField label="دلیل" name="reason" required error={e.reason} placeholder="مثلاً لغو سفارش" />
        <TextField label="شماره‌ی پیگیری" name="reference" ltr error={e.reference} />
      </div>
      <div className="flex gap-2">
        <Button type="submit" size="sm" variant="danger" loading={pending}>ثبت بازگشت وجه</Button>
        <Button size="sm" variant="ghost" onClick={onClose}>بستن</Button>
      </div>
    </form>
  );
}

function WalletPayForm({ order, walletBalance }: { order: Order; walletBalance: number }) {
  const [key, renew] = useSubmissionKey();
  const [state, action, pending] = useActionState<FormState, FormData>(async (prev, formData) => {
    const result = await payOrderWithWallet(order.id, prev, formData);
    if (result.ok) renew();

    return result;
  }, { ok: false });
  const max = Math.min(walletBalance, order.remaining_due);

  return (
    <form key={key} action={action} className="flex flex-col gap-3 border-t border-border p-5">
      <p className="text-sm font-semibold">پرداخت از کیف پول باشگاه <span className="font-normal text-text-muted">(موجودی {formatMoney(walletBalance)})</span></p>
      <FormStatus state={state} />
      <input type="hidden" name="idempotency_key" value={key} />
      <div className="flex flex-wrap items-end gap-3">
        <div className="w-56"><MoneyField label="مبلغ (تومان)" name="amount" defaultValue={rialToTomanInput(max)} error={state.errors?.amount} /></div>
        <Button type="submit" size="sm" loading={pending}>کسر از کیف پول</Button>
      </div>
    </form>
  );
}

function PaymentRow({ payment, canRefund }: { payment: Payment; canRefund: boolean }) {
  const [refunding, setRefunding] = useState(false);

  return (
    <li className="flex flex-col gap-1 py-3">
      <div className="flex items-center justify-between gap-2">
        <span className="font-medium">{payment.method_label}</span>
        <span className="flex items-center gap-2">
          <span className="font-semibold">{formatMoney(payment.amount)}</span>
          <Badge tone={STATUS_TONE[payment.status]}>{payment.status_label}</Badge>
        </span>
      </div>
      <p className="text-xs text-text-muted">
        {formatJalaliDateTime(payment.paid_at ?? payment.created_at)}
        {payment.ref_id ? <> • کد پیگیری <Ltr>{payment.ref_id}</Ltr></> : null}
        {payment.card_pan ? <> • کارت <Ltr>{payment.card_pan}</Ltr></> : null}
        {payment.reference ? <> • رسید <Ltr>{payment.reference}</Ltr></> : null}
      </p>
      {payment.failure_message ? <p className="text-xs text-danger">{payment.failure_message}</p> : null}
      {payment.note ? <p className="text-xs">{payment.note}</p> : null}
      {payment.refunds?.map((r) => (
        <p key={r.id} className="text-xs text-warning">
          بازگشت {formatMoney(r.amount)} ({r.method_label}) • {r.reason} • {formatJalaliDateTime(r.created_at)}
        </p>
      ))}
      {canRefund && payment.refundable > 0 ? (
        refunding ? <RefundForm payment={payment} onClose={() => setRefunding(false)} /> : (
          <div><Button size="sm" variant="ghost" onClick={() => setRefunding(true)}>بازگشت وجه</Button></div>
        )
      ) : null}
    </li>
  );
}

export function OrderPayments({ order, payments, canRecord, canRefund, walletBalance = null }: { order: Order; payments: Payment[]; canRecord: boolean; canRefund: boolean; walletBalance?: number | null }) {
  const closed = order.status === 'cancelled' || order.status === 'rejected';

  return (
    <Card>
      <CardHeader title="پرداخت‌ها" description={order.payment_status_label} />
      <dl className="grid grid-cols-2 gap-y-2 px-5 pt-4 text-sm">
        <dt className="text-text-muted">پرداخت‌شده</dt><dd className="text-end">{formatMoney(order.paid_total)}</dd>
        {order.refunded_total > 0 ? <><dt className="text-text-muted">بازگشت داده‌شده</dt><dd className="text-end text-warning">{formatMoney(order.refunded_total)}</dd></> : null}
        <dt className="font-semibold">باقی‌مانده</dt><dd className="text-end font-semibold">{formatMoney(order.remaining_due)}</dd>
      </dl>
      {order.needs_refund ? (
        <div className="px-5 pt-3"><Alert tone="danger">برای این سفارش پولی دریافت شده که باید به مشتری برگردد.</Alert></div>
      ) : null}
      {payments.length > 0 ? (
        <ul className="divide-y divide-border px-5 text-sm">
          {payments.map((p) => <PaymentRow key={p.id} payment={p} canRefund={canRefund} />)}
        </ul>
      ) : (
        <p className="px-5 py-4 text-sm text-text-muted">هنوز پرداختی ثبت نشده است.</p>
      )}
      {canRecord && !closed && order.remaining_due > 0 && walletBalance !== null && walletBalance > 0 ? <WalletPayForm order={order} walletBalance={walletBalance} /> : null}
      {canRecord && !closed && order.remaining_due > 0 ? <RecordPaymentForm order={order} /> : null}
    </Card>
  );
}
