'use client';

import Link from 'next/link';
import { useState, useTransition } from 'react';
import { Alert, Badge, Button, Card } from '@cafe/ui';
import { formatJalaliDateTime, formatMoney, formatNumber, formatTime } from '@cafe/locale';
import { acknowledgeTableRequest, transitionOrder } from '@/app/actions/commerce';
import type { Order, TableRequestItem } from '@/lib/types';

function minutesSince(iso: string): number {
  return Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));
}

function isAhead(iso: string | null): boolean {
  return iso !== null && new Date(iso).getTime() > Date.now();
}

function isToday(iso: string): boolean {
  return new Date(iso).toDateString() === new Date().toDateString();
}

const NEEDS_REASON = new Set(['cancelled', 'rejected']);

export function OrderCard({ order, canManage }: { order: Order; canManage: boolean }) {
  const [pending, start] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const [reasonFor, setReasonFor] = useState<string | null>(null);
  const [reason, setReason] = useState('');
  // A pre-order is timed from its slot, not from when it was placed.
  const scheduledAhead = isAhead(order.scheduled_for);
  const waited = minutesSince(order.scheduled_for && !scheduledAhead ? order.scheduled_for : order.placed_at);
  const late = !scheduledAhead && waited >= 15 && ['placed', 'accepted', 'preparing'].includes(order.status);
  const sameDay = order.scheduled_for ? isToday(order.scheduled_for) : true;

  const move = (status: string, note?: string) =>
    start(async () => {
      const result = await transitionOrder(order.id, status, note);
      setError(result.ok ? null : result.message ?? null);
      if (result.ok) setReasonFor(null);
    });

  const forward = order.next_statuses.filter((s) => !NEEDS_REASON.has(s.value));
  const backward = order.next_statuses.filter((s) => NEEDS_REASON.has(s.value));

  return (
    <Card className={`flex flex-col gap-2 p-4 ${late ? 'border-warning' : ''}`}>
      <div className="flex items-start justify-between gap-2">
        <div>
          <Link href={`/dashboard/orders/${order.id}`} className="text-lg font-bold hover:underline">
            #{formatNumber(order.daily_number)}
          </Link>
          <p className="text-xs text-text-muted">
            {order.type_label}
            {order.table ? ` • ${order.table.label}` : ''}
            {order.branch ? ` • ${order.branch.name}` : ''}
          </p>
        </div>
        <div className="text-end">
          {scheduledAhead && order.scheduled_for ? (
            <span className="inline-flex flex-col items-end rounded-xl bg-info-soft px-2.5 py-1 text-info">
              <span className="text-[10px] font-medium">تحویل {sameDay ? 'امروز' : formatJalaliDateTime(order.scheduled_for).split(' ساعت')[0]}</span>
              <span className="tabular text-base font-black leading-5">{formatTime(order.scheduled_for)}</span>
            </span>
          ) : (
            <>
              <Badge tone={late ? 'warning' : 'neutral'}>{formatNumber(waited)} دقیقه</Badge>
              {order.scheduled_for ? <p className="mt-1 text-xs text-info">پیش‌سفارش {formatTime(order.scheduled_for)}</p> : null}
            </>
          )}
        </div>
      </div>

      <ul className="text-sm">
        {order.items?.map((item) => (
          <li key={item.id}>
            <span className="font-medium">{formatNumber(item.quantity)}× {item.product_name}</span>
            {item.variant_name ? <span className="text-text-muted"> ({item.variant_name})</span> : null}
            {item.modifiers.length ? <span className="block text-xs text-text-muted">{item.modifiers.map((m) => m.name).join('، ')}</span> : null}
            {item.note ? <span className="block text-xs text-warning">یادداشت: {item.note}</span> : null}
          </li>
        ))}
      </ul>

      {order.customer_note ? <p className="rounded-md bg-warning-soft px-2 py-1 text-xs text-warning">{order.customer_note}</p> : null}

      <div className="flex items-center justify-between text-sm">
        <span className="font-semibold">{formatMoney(order.total)}</span>
        <span className="flex gap-1">
          {order.needs_refund ? <Badge tone="danger">نیاز به بازگشت وجه</Badge> : null}
          <Badge tone={order.payment_status === 'paid' ? 'success' : 'neutral'}>{order.payment_status_label}</Badge>
        </span>
      </div>

      {error ? <Alert tone="danger">{error}</Alert> : null}

      {canManage ? (
        reasonFor ? (
          <div className="flex flex-col gap-2">
            <label htmlFor={`reason-${order.id}`} className="text-xs font-medium">دلیل {reasonFor === 'rejected' ? 'رد' : 'لغو'} سفارش</label>
            <input
              id={`reason-${order.id}`}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              className="h-9 rounded-md border border-border-strong bg-surface px-2 text-sm"
              placeholder="مثلاً تمام شدن مواد"
            />
            <div className="flex gap-2">
              <Button size="sm" variant="danger" loading={pending} disabled={!reason.trim()} onClick={() => move(reasonFor, reason.trim())}>تأیید</Button>
              <Button size="sm" variant="ghost" onClick={() => setReasonFor(null)}>انصراف</Button>
            </div>
          </div>
        ) : (
          <div className="flex flex-wrap gap-2">
            {forward.map((s) => (
              <Button key={s.value} size="sm" loading={pending} onClick={() => move(s.value)}>{s.label}</Button>
            ))}
            {backward.map((s) => (
              <Button key={s.value} size="sm" variant="ghost" onClick={() => { setReason(''); setReasonFor(s.value); }}>
                {s.value === 'rejected' ? 'رد' : 'لغو'}
              </Button>
            ))}
          </div>
        )
      ) : null}
    </Card>
  );
}

export function TableRequestList({ requests, canManage }: { requests: TableRequestItem[]; canManage: boolean }) {
  const [pending, start] = useTransition();

  return (
    <div role="region" aria-label="درخواست‌های میزها" className="flex flex-wrap gap-2">
      {requests.map((r) => (
        <div key={r.id} className="flex items-center gap-2 rounded-md bg-warning-soft px-3 py-2 text-sm text-warning">
          <span className="font-semibold">{r.table.label}</span>
          <span>{r.type_label}</span>
          <span className="text-xs">{formatTime(r.created_at)}</span>
          {canManage ? (
            <Button size="sm" variant="secondary" loading={pending} onClick={() => start(() => acknowledgeTableRequest(r.id))}>رسیدگی شد</Button>
          ) : null}
        </div>
      ))}
    </div>
  );
}

export function OrderTimeline({ order }: { order: Order }) {
  return (
    <ol className="flex flex-col gap-2 text-sm">
      {order.history?.map((h, i) => (
        <li key={i} className="flex justify-between gap-3">
          <span>{h.to_label}{h.note ? <span className="text-text-muted"> • {h.note}</span> : null}</span>
          <span className="text-xs text-text-muted">{formatJalaliDateTime(h.at)}</span>
        </li>
      ))}
    </ol>
  );
}
