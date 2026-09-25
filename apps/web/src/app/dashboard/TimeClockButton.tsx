'use client';

import { useEffect, useRef, useState, useTransition } from 'react';
import { LogIn, LogOut, Timer } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatTime } from '@cafe/locale';
import { loadTimeClock, punch } from '@/app/actions/operations';
import { formatHours, type TimeClock } from '@/lib/operations-types';

/**
 * Self clock-in/out for staff linked to an employee. Renders nothing for everyone else. Clock-out
 * asks for a second tap (no browser dialog) so a stray click doesn't end a shift.
 */
export function TimeClockButton() {
  const [clock, setClock] = useState<TimeClock | null>(null);
  const [now, setNow] = useState<number | null>(null);
  const [confirming, setConfirming] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [pending, start] = useTransition();
  const confirmTimer = useRef<ReturnType<typeof setTimeout>>(undefined);

  useEffect(() => {
    let alive = true;
    loadTimeClock().then((c) => { if (alive) setClock(c); }).catch(() => {});
    const tick = () => setNow(Date.now());
    const first = setTimeout(tick, 0);
    const timer = setInterval(tick, 30_000);

    return () => { alive = false; clearTimeout(first); clearInterval(timer); clearTimeout(confirmTimer.current); };
  }, []);

  useEffect(() => {
    if (!message) return;
    const t = setTimeout(() => setMessage(null), 4000);

    return () => clearTimeout(t);
  }, [message]);

  if (!clock) return null;

  const open = clock.open;
  const minutes = open && now !== null ? Math.max(0, Math.floor((now - new Date(open.clock_in_at).getTime()) / 60_000)) : null;

  const act = () => {
    if (open && !confirming) {
      setConfirming(true);
      clearTimeout(confirmTimer.current);
      confirmTimer.current = setTimeout(() => setConfirming(false), 4000);

      return;
    }
    setConfirming(false);
    start(async () => {
      const r = await punch(open ? 'out' : 'in');
      if (r.ok && r.clock !== undefined) setClock(r.clock);
      setMessage({ ok: r.ok, text: r.message ?? (r.ok ? 'ثبت شد.' : 'ثبت نشد.') });
    });
  };

  const title = open
    ? `ورود ${formatTime(open.clock_in_at)}${open.late_minutes ? ` • ${new Intl.NumberFormat('fa-IR').format(open.late_minutes)} دقیقه تأخیر` : ''}`
    : clock.next_shift ? `شیفت بعدی: ${formatTime(clock.next_shift.starts_at)} تا ${formatTime(clock.next_shift.ends_at)}` : 'ثبت ورود';

  return (
    <div className="relative">
      <button type="button" onClick={act} disabled={pending} title={title} aria-live="polite"
        className={cx('inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-sm font-medium transition-colors disabled:opacity-60',
          open ? (confirming ? 'border-danger bg-danger-soft text-danger' : 'border-success/40 bg-success-soft text-success hover:border-success')
            : 'border-brand bg-brand text-on-brand hover:bg-brand-strong')}>
        {open ? (confirming ? <LogOut className="size-4" aria-hidden="true" /> : <Timer className="size-4" aria-hidden="true" />) : <LogIn className="size-4" aria-hidden="true" />}
        {open ? (
          confirming ? 'تأیید خروج' : <><span className="hidden sm:inline">سر کار</span>{minutes !== null ? <span className="tabular">{formatHours(minutes)}</span> : null}</>
        ) : 'ورود'}
      </button>
      {message ? (
        <p role="status" className={cx('dialog-in absolute end-0 top-full z-40 mt-2 whitespace-nowrap rounded-lg px-3 py-2 text-xs font-medium shadow-[var(--shadow-md)]', message.ok ? 'bg-success text-on-brand' : 'bg-danger text-on-brand')}>
          {message.text}
        </p>
      ) : null}
    </div>
  );
}
