'use client';

import { useEffect, useState } from 'react';
import { Timer } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatNumber, toPersianDigits } from '@cafe/locale';
import { now } from '@/lib/clock';

const pad = (n: number) => String(n).padStart(2, '0');

/** "۰۲:۱۴:۰۹" under a day, "۳ روز" beyond. Rendered only after mount (the seconds would never hydrate). */
export function Countdown({ endsAt, className, label = 'تا پایان' }: { endsAt: number; className?: string; label?: string }) {
  const [left, setLeft] = useState<number | null>(null);

  useEffect(() => {
    const tick = () => setLeft(Math.max(0, endsAt - now()));
    tick();
    const id = setInterval(tick, 1000);

    return () => clearInterval(id);
  }, [endsAt]);

  if (left === null) return <span className={cx('inline-flex h-6 min-w-24', className)} aria-hidden="true" />;

  const s = Math.floor(left / 1000);
  const days = Math.floor(s / 86400);
  const text = days >= 1 ? `${formatNumber(days)} روز` : toPersianDigits(`${pad(Math.floor(s / 3600))}:${pad(Math.floor((s % 3600) / 60))}:${pad(s % 60)}`);

  return (
    <span className={cx('tabular inline-flex items-center gap-1 whitespace-nowrap', className)} role="timer" aria-live="off">
      <Timer className="size-3.5 shrink-0" aria-hidden="true" />
      <span className="sr-only">{label}</span>
      <span dir="ltr">{text}</span>
    </span>
  );
}
