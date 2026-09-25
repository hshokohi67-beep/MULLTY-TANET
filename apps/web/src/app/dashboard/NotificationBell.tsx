'use client';

import Link from 'next/link';
import { useCallback, useEffect, useRef, useState } from 'react';
import { ArrowUpLeft, Bell, BellRing, CircleAlert, Info, TriangleAlert } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatNumber } from '@cafe/locale';
import { loadAlerts, type AlertItem } from '@/app/actions/palette';

const REFRESH_MS = 60_000;

/**
 * What needs attention, from anywhere in the panel: the same actionable alerts as the overview,
 * refreshed every minute while the tab is visible and whenever the panel is opened.
 */
export function NotificationBell() {
  const [alerts, setAlerts] = useState<AlertItem[] | null>(null);
  const [open, setOpen] = useState(false);
  const box = useRef<HTMLDivElement>(null);

  // Background refreshes pause while the tab is hidden; an explicit open or the first load always fetch.
  const refresh = useCallback(async (force = false) => {
    if (!force && document.hidden) return;
    setAlerts(await loadAlerts());
  }, []);

  useEffect(() => {
    const first = setTimeout(() => void refresh(true), 0);
    const timer = setInterval(() => void refresh(), REFRESH_MS);
    const onShow = () => { if (!document.hidden) void refresh(); };
    document.addEventListener('visibilitychange', onShow);

    return () => { clearTimeout(first); clearInterval(timer); document.removeEventListener('visibilitychange', onShow); };
  }, [refresh]);

  // Close on outside click / Esc.
  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => { if (box.current && !box.current.contains(e.target as Node)) setOpen(false); };
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onKey);

    return () => { document.removeEventListener('mousedown', onDown); document.removeEventListener('keydown', onKey); };
  }, [open]);

  const count = alerts?.length ?? 0;
  const urgent = alerts?.some((a) => a.severity === 'danger') ?? false;

  return (
    <div ref={box} className="relative">
      <button type="button" onClick={() => { setOpen((o) => !o); if (!open) void refresh(true); }} aria-expanded={open} aria-haspopup="dialog"
        aria-label={count ? `اعلان‌ها: ${formatNumber(count)} مورد نیازمند توجه` : 'اعلان‌ها'}
        className="relative flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted hover:text-text">
        {count ? <BellRing className="size-5" /> : <Bell className="size-5" />}
        {count ? (
          <span className={cx('absolute -end-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold text-white ring-2 ring-bg', urgent ? 'bg-danger' : 'bg-warning')}>
            {formatNumber(count)}
          </span>
        ) : null}
      </button>

      {open ? (
        <div role="dialog" aria-label="اعلان‌ها" className="dialog-in absolute end-0 top-full z-40 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-border bg-surface-raised shadow-[var(--shadow-lg)]">
          <p className="border-b border-border px-4 py-3 text-sm font-semibold">نیازمند توجه</p>
          {alerts === null ? (
            <p className="px-4 py-8 text-center text-sm text-text-muted">در حال دریافت…</p>
          ) : alerts.length === 0 ? (
            <div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
              <span className="flex size-10 items-center justify-center rounded-full bg-success-soft text-success"><Bell className="size-5" aria-hidden="true" /></span>
              <p className="text-sm font-medium">همه‌چیز مرتب است</p>
              <p className="text-xs text-text-muted">سفارش معطل، میز منتظر یا کار عقب‌افتاده‌ای نیست.</p>
            </div>
          ) : (
            <ul className="max-h-96 overflow-y-auto p-1.5">
              {alerts.map((a) => {
                const Icon = a.severity === 'danger' ? CircleAlert : a.severity === 'warning' ? TriangleAlert : Info;
                const tone = a.severity === 'danger' ? 'bg-danger-soft text-danger' : a.severity === 'warning' ? 'bg-warning-soft text-warning' : 'bg-info-soft text-info';

                return (
                  <li key={a.type}>
                    <Link href={a.href} onClick={() => setOpen(false)} className="group flex items-center gap-3 rounded-xl px-2.5 py-2.5 hover:bg-surface-muted">
                      <span className={cx('flex size-8 shrink-0 items-center justify-center rounded-lg', tone)}><Icon className="size-4" aria-hidden="true" /></span>
                      <span className="flex-1 text-sm">{a.title}</span>
                      <ArrowUpLeft className="size-4 text-text-subtle transition-transform group-hover:-translate-x-0.5 group-hover:-translate-y-0.5" aria-hidden="true" />
                    </Link>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      ) : null}
    </div>
  );
}
