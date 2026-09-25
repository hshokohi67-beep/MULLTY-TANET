'use client';

import { useActionState, useState, useTransition } from 'react';
import { PackageCheck, Send, X } from 'lucide-react';
import { Alert, Button, SelectField } from '@cafe/ui';
import { paySupplier, purchaseAction } from '@/app/actions/inventory';
import { FormStatus } from '@/components/FormStatus';
import { MoneyField } from '@/components/MoneyField';
import { useSubmissionKey } from '@/components/useSubmissionKey';
import type { PurchaseOrder } from '@/lib/inventory-types';
import type { FormState } from '@/lib/types';
import { rialToTomanInput } from '@/lib/money';

/** Order / receive everything / cancel. Receiving adds the stock and updates average costs. */
export function PurchaseActions({ po }: { po: PurchaseOrder }) {
  const [pending, start] = useTransition();
  const [message, setMessage] = useState<FormState | null>(null);
  const [key, renewKey] = useSubmissionKey();
  const [confirmCancel, setConfirmCancel] = useState(false);
  const run = (action: 'order' | 'cancel' | 'receive') => start(async () => {
    const r = await purchaseAction(po.id, action, action === 'receive' ? key : undefined);
    setMessage(r);
    renewKey();
    setConfirmCancel(false);
  });

  if (po.status === 'received' || po.status === 'cancelled') return null;

  return (
    <div className="flex flex-wrap items-center gap-2">
      {message && !message.ok ? <Alert tone="danger">{message.message}</Alert> : null}
      {po.status === 'draft' ? <Button variant="secondary" icon={<Send />} loading={pending} onClick={() => run('order')}>ثبت سفارش</Button> : null}
      <Button icon={<PackageCheck />} loading={pending} onClick={() => run('receive')}>تحویل گرفتم</Button>
      {confirmCancel ? (
        <>
          <Button variant="danger" loading={pending} onClick={() => run('cancel')}>لغو شود</Button>
          <Button variant="ghost" onClick={() => setConfirmCancel(false)}>نه</Button>
        </>
      ) : <Button variant="ghost" icon={<X />} onClick={() => setConfirmCancel(true)}>لغو</Button>}
    </div>
  );
}

export function PaymentForm({ id, due }: { id: string; due: number }) {
  const [state, action, pending] = useActionState<FormState, FormData>(paySupplier.bind(null, id), { ok: false });

  return (
    <form action={action} className="flex flex-col gap-3">
      <FormStatus state={state} />
      <MoneyField label="مبلغ پرداخت (تومان)" name="amount" defaultValue={rialToTomanInput(due)} required error={state.errors?.amount} />
      <SelectField label="روش" name="method" defaultValue="transfer">
        <option value="transfer">حواله‌ی بانکی</option>
        <option value="card">کارت به کارت</option>
        <option value="cash">نقد</option>
      </SelectField>
      <Button type="submit" loading={pending}>ثبت پرداخت</Button>
    </form>
  );
}
