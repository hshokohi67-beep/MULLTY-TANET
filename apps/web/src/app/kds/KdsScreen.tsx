'use client';

import { useCallback, useEffect, useMemo, useRef, useState, useTransition } from 'react';
import { formatNumber, formatTime, toPersianDigits } from '@cafe/locale';
import { kdsAcknowledge, kdsBump, kdsItem, unpairDevice } from '@/app/actions/kds';
import type { KdsBoard, KdsItem, KdsMe, KdsOrder } from '@/lib/types';

const POLL_MS = 3000;

/* ---------- small inline icons (the design phase brings a shared icon set) ---------- */
const Icon = {
  bell: <path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0" />,
  bellOff: <path d="M8.7 3A6 6 0 0 1 18 8a21 21 0 0 0 .6 5M17 17H3s3-2 3-9a4.7 4.7 0 0 1 .3-2M10.3 21a1.94 1.94 0 0 0 3.4 0M2 2l20 20" />,
  expand: <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />,
  check: <path d="M20 6 9 17l-5-5" />,
  undo: <path d="M9 14 4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11" />,
  hand: <path d="M18 11V6a2 2 0 0 0-4 0M14 10V4a2 2 0 0 0-4 0v2M10 10.5V6a2 2 0 0 0-4 0v8M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.9-6-2.4l-3.6-3.6a2 2 0 0 1 2.8-2.8L7 15" />,
};

function Svg({ d, className = 'size-5' }: { d: React.ReactNode; className?: string }) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {d}
    </svg>
  );
}

/* ---------- sound: a soft two-note chime, generated (no audio files to load) ---------- */
function chime(kind: 'order' | 'call') {
  try {
    const ctx = new AudioContext();
    const notes = kind === 'order' ? [880, 1320] : [660, 660, 660];
    notes.forEach((freq, i) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = freq;
      const t = ctx.currentTime + i * 0.18;
      gain.gain.setValueAtTime(0.0001, t);
      gain.gain.exponentialRampToValueAtTime(0.25, t + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.35);
      osc.connect(gain).connect(ctx.destination);
      osc.start(t);
      osc.stop(t + 0.4);
    });
    setTimeout(() => void ctx.close(), 1200);
  } catch {
    // Audio blocked or unsupported: the screen still works silently.
  }
}

function mmss(ms: number): string {
  const total = Math.max(0, Math.floor(ms / 1000));
  const m = Math.floor(total / 60);
  const s = total % 60;

  return toPersianDigits(`${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`);
}

type Urgency = 'fresh' | 'warm' | 'late';

function urgency(elapsedMs: number, lateAfterMinutes: number): Urgency {
  const ratio = elapsedMs / (lateAfterMinutes * 60_000);

  return ratio >= 1 ? 'late' : ratio >= 0.6 ? 'warm' : 'fresh';
}

const URGENCY_STYLE: Record<Urgency, string> = {
  fresh: 'border-t-success',
  warm: 'border-t-warning',
  late: 'border-t-danger',
};

/* ---------- item row: one tap moves it forward ---------- */
function ItemRow({ item, busy, onAct }: { item: KdsItem; busy: boolean; onAct: (action: 'start' | 'ready' | 'recall') => void }) {
  const next = item.status === 'queued' ? 'start' : item.status === 'preparing' ? 'ready' : null;
  const label = item.status === 'queued' ? 'شروع' : item.status === 'preparing' ? 'آماده شد' : null;

  return (
    <li className={`flex items-stretch gap-2 ${item.status === 'cancelled' ? 'opacity-50 line-through' : ''}`}>
      <button
        type="button"
        disabled={!next || busy}
        onClick={() => next && onAct(next)}
        className={`flex flex-1 items-start gap-3 rounded-md px-2 py-2 text-start transition-colors duration-[var(--duration-fast)] ${
          next ? 'hover:bg-surface-muted active:bg-border' : ''
        } ${item.status === 'preparing' ? 'bg-warning-soft/40' : ''}`}
        aria-label={label ? `${item.name}: ${label}` : item.name}
      >
        <span className={`mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full text-base font-bold ${
          item.status === 'ready' ? 'bg-success text-bg' : item.status === 'preparing' ? 'bg-warning text-bg' : 'bg-surface-muted'
        }`}>
          {item.status === 'ready' ? <Svg d={Icon.check} className="size-4" /> : formatNumber(item.quantity)}
        </span>
        <span className="flex-1">
          <span className="block text-lg font-semibold leading-snug">
            {item.name}{item.variant ? <span className="font-normal text-text-muted"> • {item.variant}</span> : null}
          </span>
          {item.modifiers.length ? <span className="block text-sm text-text-muted">{item.modifiers.join('، ')}</span> : null}
          {item.note ? <span className="mt-0.5 block text-sm font-medium text-warning">«{item.note}»</span> : null}
        </span>
        {label ? <span className="self-center rounded-full border border-border-strong px-2.5 py-1 text-xs text-text-muted">{label}</span> : null}
      </button>
      {item.status === 'ready' ? (
        <button type="button" disabled={busy} onClick={() => onAct('recall')} className="rounded-md px-2 text-text-subtle hover:bg-surface-muted hover:text-text" aria-label={`برگرداندن ${item.name} به آماده‌سازی`} title="برگرداندن">
          <Svg d={Icon.undo} className="size-4" />
        </button>
      ) : null}
    </li>
  );
}

/* ---------- order card ---------- */
function OrderCard({ order, now, lateAfter, busy, onItem, onBump }: {
  order: KdsOrder;
  now: number;
  lateAfter: number;
  busy: boolean;
  onItem: (item: KdsItem, action: 'start' | 'ready' | 'recall') => void;
  onBump: () => void;
}) {
  const start = new Date(order.scheduled_for && new Date(order.scheduled_for).getTime() > new Date(order.placed_at).getTime() ? order.scheduled_for : order.placed_at).getTime();
  const elapsed = now - start;
  const level = order.state === 'open' ? urgency(elapsed, lateAfter) : 'fresh';
  const openItems = order.items.filter((i) => i.status === 'queued' || i.status === 'preparing');
  const waitingForSchedule = order.scheduled_for !== null && start > now;

  return (
    <article
      className={`kds-card flex flex-col rounded-lg border border-border border-t-4 bg-surface shadow-[var(--shadow-md)] ${URGENCY_STYLE[level]} ${
        order.state !== 'open' ? 'opacity-60' : ''
      } ${level === 'late' ? 'kds-late' : ''}`}
      aria-label={`سفارش ${formatNumber(order.daily_number)}`}
    >
      <header className="flex items-start justify-between gap-2 border-b border-border px-4 py-3">
        <div>
          <p className="text-3xl font-black leading-none tracking-tight">#{formatNumber(order.daily_number)}</p>
          <p className="mt-1.5 text-sm text-text-muted">
            {order.table ?? order.type_label}
            {order.customer_name ? ` • ${order.customer_name}` : ''}
          </p>
        </div>
        <div className="text-end">
          {order.state === 'open' ? (
            <p className={`text-2xl font-bold tabular-nums ${level === 'late' ? 'text-danger' : level === 'warm' ? 'text-warning' : 'text-text'}`}>
              {waitingForSchedule ? formatTime(order.scheduled_for as string) : mmss(elapsed)}
            </p>
          ) : (
            <p className={`rounded-full px-2.5 py-1 text-sm font-semibold ${order.state === 'cancelled' ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success'}`}>
              {order.state === 'cancelled' ? 'لغو شد' : 'آماده شد'}
            </p>
          )}
          <div className="mt-1 flex justify-end gap-1">
            {order.tier ? <span className="rounded-full bg-brand-soft px-2 py-0.5 text-xs font-medium text-brand">{order.tier}</span> : null}
            {order.scheduled_for ? <span className="rounded-full bg-info-soft px-2 py-0.5 text-xs text-info">پیش‌سفارش</span> : null}
          </div>
        </div>
      </header>

      {order.note ? (
        <p className="mx-3 mt-3 rounded-md bg-warning-soft px-3 py-2 text-sm font-medium text-warning">یادداشت: {order.note}</p>
      ) : null}

      <ul className="flex flex-1 flex-col gap-1 p-2">
        {order.items.map((item) => <ItemRow key={item.id} item={item} busy={busy} onAct={(action) => onItem(item, action)} />)}
      </ul>

      {order.state === 'open' && openItems.length > 0 ? (
        <footer className="border-t border-border p-2">
          <button
            type="button"
            disabled={busy}
            onClick={onBump}
            className="flex h-12 w-full items-center justify-center gap-2 rounded-md bg-brand text-base font-semibold text-on-brand transition-opacity hover:opacity-90 disabled:opacity-50"
          >
            <Svg d={Icon.check} /> همه آماده شد
          </button>
        </footer>
      ) : null}
    </article>
  );
}

/* ---------- the screen ---------- */
export function KdsScreen({ me, isDevice }: { me: KdsMe; isDevice: boolean }) {
  const [branchId, setBranchId] = useState(me.branches[0]?.id ?? '');
  const branch = me.branches.find((b) => b.id === branchId) ?? me.branches[0];
  const [stationId, setStationId] = useState<string>(me.actor.station_id ?? '');
  const [board, setBoard] = useState<KdsBoard | null>(null);
  const [online, setOnline] = useState(true);
  const [soundOn, setSoundOn] = useState(false);
  const [now, setNow] = useState(() => Date.now());
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();
  const etag = useRef<string | null>(null);
  const skew = useRef(0);
  const known = useRef<{ orders: Set<string>; calls: Set<string> } | null>(null);
  const soundRef = useRef(false);

  const refresh = useCallback(async (force = false) => {
    const query = new URLSearchParams();
    if (branchId) query.set('branch_id', branchId);
    if (stationId) query.set('station_id', stationId);

    try {
      const response = await fetch(`/kds/board?${query}`, { headers: !force && etag.current ? { 'If-None-Match': etag.current } : {}, cache: 'no-store' });
      if (response.status === 304) {
        setOnline(true);
        return;
      }
      if (!response.ok) throw new Error(String(response.status));

      etag.current = response.headers.get('ETag');
      const next = (await response.json()).data as KdsBoard;
      skew.current = new Date(next.server_time).getTime() - Date.now();

      // Chime for orders and calls that weren't there before (not on the first load).
      const orderIds = new Set(next.orders.filter((o) => o.state === 'open').map((o) => o.order_id));
      const callIds = new Set(next.table_requests.map((c) => c.id));
      if (known.current && soundRef.current) {
        if ([...orderIds].some((id) => !known.current?.orders.has(id))) chime('order');
        else if ([...callIds].some((id) => !known.current?.calls.has(id))) chime('call');
      }
      known.current = { orders: orderIds, calls: callIds };

      setBoard(next);
      setOnline(true);
    } catch {
      setOnline(false);
    }
  }, [branchId, stationId]);

  useEffect(() => {
    etag.current = null;
    known.current = null;
    const first = setTimeout(() => void refresh(true), 0);
    // No polling while the screen is off or the tab is hidden; check at once when it comes back.
    const poll = setInterval(() => {
      if (!document.hidden) void refresh();
    }, POLL_MS);
    const onVisibility = () => {
      if (!document.hidden) void refresh();
    };
    document.addEventListener('visibilitychange', onVisibility);
    const tick = setInterval(() => setNow(Date.now() + skew.current), 1000);

    return () => {
      clearTimeout(first);
      clearInterval(poll);
      document.removeEventListener('visibilitychange', onVisibility);
      clearInterval(tick);
    };
  }, [refresh]);

  const act = (task: () => Promise<{ ok: boolean; message?: string }>) =>
    startTransition(async () => {
      const result = await task();
      setError(result.ok ? null : result.message ?? 'خطایی رخ داد.');
      await refresh(true);
    });

  const lateFor = useMemo(() => {
    const map = new Map((board?.stations ?? []).map((s) => [s.id, s.late_after_minutes]));
    return (order: KdsOrder) => Math.min(...order.items.map((i) => map.get(i.station_id) ?? 7));
  }, [board?.stations]);

  const open = board?.orders.filter((o) => o.state === 'open') ?? [];
  const lateCount = open.filter((o) => urgency(now - new Date(o.scheduled_for ?? o.placed_at).getTime(), lateFor(o)) === 'late').length;

  return (
    <div className="flex min-h-dvh flex-col">
      <header className="sticky top-0 z-10 flex flex-wrap items-center gap-3 border-b border-border bg-surface/95 px-4 py-2 backdrop-blur">
        <h1 className="text-lg font-bold">آشپزخانه</h1>

        {me.branches.length > 1 && !isDevice ? (
          <select value={branchId} onChange={(e) => { setBranchId(e.target.value); setStationId(''); }} aria-label="شعبه" className="h-9 rounded-md border border-border-strong bg-surface px-2 text-sm">
            {me.branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </select>
        ) : null}

        {!me.actor.station_id && (branch?.stations.length ?? 0) > 1 ? (
          <nav className="flex gap-1 rounded-full bg-surface-muted p-1" aria-label="ایستگاه">
            {[{ id: '', name: 'همه' }, ...(branch?.stations ?? [])].map((s) => (
              <button
                key={s.id}
                type="button"
                onClick={() => setStationId(s.id)}
                aria-pressed={stationId === s.id}
                className={`rounded-full px-3 py-1 text-sm transition-colors ${stationId === s.id ? 'bg-brand text-on-brand' : 'text-text-muted hover:text-text'}`}
              >
                {s.name}
              </button>
            ))}
          </nav>
        ) : null}

        <div className="flex items-center gap-2 text-sm">
          <span className="rounded-full bg-surface-muted px-3 py-1">{formatNumber(open.length)} سفارش باز</span>
          {lateCount > 0 ? <span className="rounded-full bg-danger-soft px-3 py-1 font-semibold text-danger">{formatNumber(lateCount)} دیرکرد</span> : null}
        </div>

        <div className="ms-auto flex items-center gap-2">
          <span className={`size-2.5 rounded-full ${online ? 'bg-success' : 'animate-pulse bg-danger'}`} title={online ? 'متصل' : 'قطع ارتباط؛ دوباره تلاش می‌شود'} aria-label={online ? 'متصل' : 'قطع ارتباط'} />
          <span className="text-lg font-semibold tabular-nums">{formatTime(new Date(now))}</span>
          <button
            type="button"
            onClick={() => {
              const on = !soundOn;
              soundRef.current = on;
              setSoundOn(on);
              if (on) chime('order'); // also unlocks audio on tablets, which need a tap first
            }}
            className={`flex h-9 items-center gap-1.5 rounded-md px-3 text-sm ${soundOn ? 'bg-brand-soft text-brand' : 'bg-surface-muted text-text-muted'}`}
            aria-pressed={soundOn}
          >
            <Svg d={soundOn ? Icon.bell : Icon.bellOff} className="size-4" /> {soundOn ? 'صدا روشن' : 'روشن کردن صدا'}
          </button>
          <button type="button" onClick={() => void (document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen())} className="flex size-9 items-center justify-center rounded-md bg-surface-muted text-text-muted hover:text-text" aria-label="تمام‌صفحه">
            <Svg d={Icon.expand} className="size-4" />
          </button>
          {isDevice ? (
            <form action={unpairDevice}>
              <button type="submit" className="h-9 rounded-md px-2 text-xs text-text-subtle hover:text-danger">قطع اتصال</button>
            </form>
          ) : null}
        </div>
      </header>

      {error ? (
        <div role="alert" className="mx-4 mt-3 rounded-md bg-danger-soft px-4 py-2 text-sm text-danger">{error}</div>
      ) : null}

      {board && board.table_requests.length > 0 ? (
        <section aria-label="درخواست‌های میز" className="flex flex-wrap gap-2 px-4 pt-3">
          {board.table_requests.map((c) => (
            <div key={c.id} className="kds-call flex items-center gap-3 rounded-lg border border-warning bg-warning-soft px-3 py-2">
              <Svg d={Icon.hand} className="size-5 text-warning" />
              <span className="font-semibold">{c.table}</span>
              <span className="text-sm text-text-muted">{c.type_label} • {mmss(now - new Date(c.created_at).getTime())}</span>
              <button type="button" disabled={pending} onClick={() => act(() => kdsAcknowledge(c.id))} className="rounded-md bg-warning px-3 py-1 text-sm font-semibold text-bg">دیدم</button>
            </div>
          ))}
        </section>
      ) : null}

      <main id="main" className="flex-1 p-4">
        {!board ? (
          <p className="py-24 text-center text-text-muted">در حال دریافت سفارش‌ها…</p>
        ) : board.orders.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-3 py-24 text-center">
            <span className="text-5xl" aria-hidden="true">☕</span>
            <p className="text-xl font-semibold">سفارشی در صف نیست</p>
            <p className="text-text-muted">سفارش‌های تازه همین‌جا ظاهر می‌شوند{soundOn ? ' و صدا پخش می‌شود' : ''}.</p>
          </div>
        ) : (
          <div className="grid items-start gap-4 [grid-template-columns:repeat(auto-fill,minmax(280px,1fr))]">
            {board.orders.map((order) => (
              <OrderCard
                key={order.order_id}
                order={order}
                now={now}
                lateAfter={lateFor(order)}
                busy={pending}
                onItem={(item, action) => act(() => kdsItem(item.id, action))}
                onBump={() => act(() => kdsBump(order.order_id, [...new Set(order.items.filter((i) => i.status === 'queued' || i.status === 'preparing').map((i) => i.station_id))]))}
              />
            ))}
          </div>
        )}
      </main>
    </div>
  );
}
