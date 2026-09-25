import Link from 'next/link';
import { ChevronLeft, ChevronRight } from 'lucide-react';

/** Previous • label • next (RTL: "previous" points right), plus a jump back to the current period. */
export function PeriodNav({ label, prev, next, current, currentLabel }: { label: string; prev: string; next: string | null; current: string | null; currentLabel: string }) {
  const btn = 'flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-surface hover:text-text';

  return (
    <div className="flex flex-wrap items-center gap-2">
      <nav aria-label="بازه" className="flex items-center gap-1 rounded-xl border border-border bg-surface-muted p-1">
        <Link href={prev} className={btn} aria-label="قبلی"><ChevronRight className="size-4" /></Link>
        <span className="min-w-36 px-2 text-center text-sm font-semibold">{label}</span>
        {next ? <Link href={next} className={btn} aria-label="بعدی"><ChevronLeft className="size-4" /></Link> : <span className={`${btn} opacity-30`} aria-hidden="true"><ChevronLeft className="size-4" /></span>}
      </nav>
      {current ? <Link href={current} className="rounded-lg px-2.5 py-1.5 text-sm font-medium text-brand hover:bg-brand-soft">{currentLabel}</Link> : null}
    </div>
  );
}
