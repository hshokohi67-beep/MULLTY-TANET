'use client';

import { useEffect, useState } from 'react';
import { formatJalaliDateTime, formatMoney, formatNumber, formatTime } from '@cafe/locale';
import { trackOrder } from '@/app/actions/tracking';
import type { Order } from '@/lib/types';

const POLL_MS = 5000;

interface Step { status: string; label: string; hint: string }

function stepsFor(order: Order): Step[] {
  const steps: Step[] = [
    { status: 'placed', label: 'ثبت شد', hint: 'سفارش شما به کافه رسید.' },
    { status: 'accepted', label: 'پذیرفته شد', hint: 'کافه سفارش را تأیید کرد.' },
    { status: 'preparing', label: 'در حال آماده‌سازی', hint: 'باریستا و آشپزخانه مشغول آماده کردن سفارش شما هستند.' },
    { status: 'ready', label: 'آماده', hint: order.type === 'delivery' ? 'سفارش آماده‌ی ارسال است.' : order.table ? 'به‌زودی سر میزتان می‌آید.' : 'می‌توانید سفارش را تحویل بگیرید.' },
  ];

  if (order.type === 'delivery') steps.push({ status: 'out_for_delivery', label: 'ارسال شد', hint: 'پیک در راه است.' });
  steps.push({ status: 'completed', label: 'تحویل شد', hint: 'نوش جان!' });

  return steps;
}

/** Reads #t=<token>, polls the order and shows a calm, live stepper. */
export function OrderTracker({ tenant, orderId }: { tenant: string; orderId: string }) {
  const [order, setOrder] = useState<Order | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const token = new URLSearchParams(window.location.hash.slice(1)).get('t') ?? '';
    let timer: ReturnType<typeof setTimeout>;
    let stopped = false;

    const load = async () => {
      // Phone locked or tab in the background: wait, and check as soon as it's visible again.
      if (document.hidden) {
        timer = setTimeout(load, POLL_MS);
        return;
      }

      const result = await trackOrder(tenant, orderId, token);
      if (stopped) return;
      setOrder(result.order);
      setError(result.error ?? null);

      const done = result.order && ['completed', 'cancelled', 'rejected'].includes(result.order.status);
      const fatal = !result.order && result.error !== 'ارتباط برقرار نشد؛ دوباره تلاش می‌کنیم…';
      if (!done && !fatal) timer = setTimeout(load, POLL_MS);
    };

    timer = setTimeout(load, 0);

    return () => {
      stopped = true;
      clearTimeout(timer);
    };
  }, [tenant, orderId]);

  if (!order) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center gap-3 text-center">
        {error ? <p className="text-text-muted">{error}</p> : <p className="text-text-muted">در حال دریافت وضعیت سفارش…</p>}
      </div>
    );
  }

  const cancelled = order.status === 'cancelled' || order.status === 'rejected';
  const steps = stepsFor(order);
  const current = Math.max(0, steps.findIndex((s) => s.status === order.status));
  const reached = (order.history ?? []).reduce<Record<string, string>>((acc, h) => ({ ...acc, [h.to]: h.at }), {});

  return (
    <>
      <header className="text-center">
        <p className="text-sm text-text-muted">{order.branch?.name}{order.table ? ` • ${order.table.label}` : ''}</p>
        <h1 className="mt-1 text-4xl font-black">سفارش #{formatNumber(order.daily_number)}</h1>
        {error ? <p className="mt-2 text-xs text-warning">{error}</p> : null}
      </header>

      {order.scheduled_for && ['placed', 'accepted'].includes(order.status) ? (
        <p className="rounded-2xl bg-info-soft px-4 py-3 text-center text-sm text-info">
          پیش‌سفارش برای <span className="font-bold">{formatJalaliDateTime(order.scheduled_for)}</span>؛ آماده‌سازی کمی قبل از این زمان شروع می‌شود.
        </p>
      ) : null}

      {order.status === 'pending_payment' ? (
        <p className="rounded-lg bg-warning-soft px-4 py-3 text-center text-warning">سفارش منتظر پرداخت است.</p>
      ) : cancelled ? (
        <div className="rounded-lg bg-danger-soft px-4 py-4 text-center text-danger">
          <p className="text-lg font-semibold">{order.status === 'rejected' ? 'کافه این سفارش را نپذیرفت' : 'سفارش لغو شد'}</p>
          {order.cancel_reason ? <p className="mt-1 text-sm">{order.cancel_reason}</p> : null}
        </div>
      ) : (
        <ol className="flex flex-col" aria-label="مراحل سفارش">
          {steps.map((step, i) => {
            const state = i < current || order.status === 'completed' ? 'done' : i === current ? 'now' : 'next';

            return (
              <li key={step.status} className="flex gap-4" aria-current={state === 'now' ? 'step' : undefined}>
                <div className="flex flex-col items-center">
                  <span className={`flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-bold transition-colors duration-[var(--duration-base)] ${
                    state === 'done' ? 'bg-success text-white' : state === 'now' ? 'track-now bg-brand text-on-brand' : 'bg-surface-muted text-text-subtle'
                  }`}>
                    {state === 'done' ? '✓' : formatNumber(i + 1)}
                  </span>
                  {i < steps.length - 1 ? <span className={`w-0.5 flex-1 ${state === 'done' ? 'bg-success' : 'bg-border'}`} /> : null}
                </div>
                <div className="pb-7">
                  <p className={`font-semibold ${state === 'next' ? 'text-text-subtle' : ''}`}>
                    {step.label}
                    {reached[step.status] ? <span className="ms-2 text-xs font-normal text-text-muted">{formatTime(reached[step.status])}</span> : null}
                  </p>
                  {state === 'now' ? <p className="mt-0.5 text-sm text-text-muted">{step.hint}</p> : null}
                </div>
              </li>
            );
          })}
        </ol>
      )}

      <section className="rounded-lg border border-border bg-surface p-4">
        <ul className="flex flex-col gap-2 text-sm">
          {order.items?.map((item) => (
            <li key={item.id} className="flex justify-between gap-3">
              <span>{formatNumber(item.quantity)}× {item.product_name}{item.variant_name ? ` (${item.variant_name})` : ''}</span>
              <span className="text-text-muted">{formatMoney(item.line_total)}</span>
            </li>
          ))}
        </ul>
        <p className="mt-3 flex justify-between border-t border-border pt-3 font-semibold">
          <span>مبلغ کل</span><span>{formatMoney(order.total)}</span>
        </p>
      </section>

      {!cancelled && order.status !== 'completed' ? (
        <p className="text-center text-xs text-text-subtle">این صفحه خودکار به‌روز می‌شود؛ لازم نیست آن را تازه کنید.</p>
      ) : null}
    </>
  );
}
