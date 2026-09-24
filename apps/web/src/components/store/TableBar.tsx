'use client';

import { useState, useTransition } from 'react';
import { BellRing, Receipt, UtensilsCrossed } from 'lucide-react';
import { tableRequest } from '@/app/actions/storefront';
import { useStore } from './StoreProvider';

/** Table mode (after a QR scan): which table we're at, plus "call the waiter" and "bring the bill". */
export function TableBar() {
  const { tenant, session, announce, reload } = useStore();
  const [pending, start] = useTransition();
  const [sent, setSent] = useState<string | null>(null);
  const table = session?.table;

  if (!table) return null;

  const send = (type: 'call_waiter' | 'request_bill') => start(async () => {
    const result = await tableRequest(tenant, type);
    if (result.ok) {
      setSent(type);
      announce(result.message ?? 'درخواست شما ارسال شد.');
    } else {
      announce(result.message, 'error');
      if (result.code === 'session_invalid') await reload();
    }
  });

  return (
    <section aria-label="سفارش از میز" className="border-b border-brand/20 bg-brand-soft">
      <div className="mx-auto flex max-w-5xl flex-wrap items-center gap-2 px-4 py-2.5">
        <UtensilsCrossed className="size-4 text-brand" aria-hidden="true" />
        <p className="flex-1 text-sm">
          <span className="font-semibold">{table.label}</span>
          <span className="text-text-muted"> • {table.branch} • سفارش شما مستقیم سر همین میز می‌آید</span>
        </p>
        <div className="flex gap-2">
          <button type="button" disabled={pending} onClick={() => send('call_waiter')}
            className="inline-flex h-9 items-center gap-1.5 rounded-full bg-surface px-3 text-sm font-medium shadow-[var(--shadow-sm)] hover:bg-surface-muted disabled:opacity-60">
            <BellRing className="size-4 text-brand" aria-hidden="true" />{sent === 'call_waiter' ? 'خبر دادیم' : 'صدا زدن گارسون'}
          </button>
          <button type="button" disabled={pending} onClick={() => send('request_bill')}
            className="inline-flex h-9 items-center gap-1.5 rounded-full bg-surface px-3 text-sm font-medium shadow-[var(--shadow-sm)] hover:bg-surface-muted disabled:opacity-60">
            <Receipt className="size-4 text-brand" aria-hidden="true" />{sent === 'request_bill' ? 'در راه است' : 'درخواست صورت‌حساب'}
          </button>
        </div>
      </div>
    </section>
  );
}
