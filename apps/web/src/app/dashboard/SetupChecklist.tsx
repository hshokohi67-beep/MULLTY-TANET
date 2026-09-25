'use client';

import Link from 'next/link';
import { useEffect, useState, useTransition } from 'react';
import { ArrowUpLeft, Check, ChevronDown, EyeOff, Sparkles } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatNumber } from '@cafe/locale';
import { skipSetupStep, type SetupData } from '@/app/actions/palette';

function Ring({ value }: { value: number }) {
  const r = 20;
  const c = 2 * Math.PI * r;

  return (
    <svg viewBox="0 0 48 48" className="size-14 shrink-0 -rotate-90" aria-hidden="true">
      <circle cx="24" cy="24" r={r} fill="none" strokeWidth="5" className="stroke-brand/15" />
      <circle cx="24" cy="24" r={r} fill="none" strokeWidth="5" strokeLinecap="round" className="stroke-brand transition-[stroke-dashoffset] duration-[var(--duration-slow)]"
        strokeDasharray={c} strokeDashoffset={c * (1 - value)} />
    </svg>
  );
}

/**
 * Setup progress from the server: a ring, the next step highlighted, essentials first. Optional
 * steps can be skipped (remembered for the café); the card disappears once everything is done.
 */
export function SetupChecklist({ setup, canSkip }: { setup: SetupData; canSkip: boolean }) {
  const [data, setData] = useState(setup);
  const [open, setOpen] = useState(true);
  const [pending, start] = useTransition();

  useEffect(() => {
    const timer = setTimeout(() => {
      try { setOpen(localStorage.getItem('setup-folded') !== '1'); } catch { /* storage unavailable */ }
    }, 0);

    return () => clearTimeout(timer);
  }, []);

  if (data.done >= data.total) return null;

  const toggle = () => setOpen((o) => {
    try { localStorage.setItem('setup-folded', o ? '1' : '0'); } catch { /* storage unavailable */ }
    return !o;
  });
  const next = data.steps.find((s) => !s.done);
  const ratio = data.total ? data.done / data.total : 0;

  return (
    <section aria-labelledby="setup-title" className="overflow-hidden rounded-2xl border border-brand/20 bg-gradient-to-l from-brand-soft/70 via-surface to-surface shadow-[var(--shadow-sm)]">
      <div className="flex items-center gap-4 px-5 py-4">
        <span className="relative">
          <Ring value={ratio} />
          <span className="tabular absolute inset-0 flex items-center justify-center text-xs font-bold text-brand">{formatNumber(Math.round(ratio * 100))}٪</span>
        </span>
        <div className="min-w-0 flex-1">
          <h2 id="setup-title" className="font-bold">راه‌اندازی کافه</h2>
          <p className="text-sm text-text-muted">{formatNumber(data.done)} از {formatNumber(data.total)} مرحله • {next ? <>قدم بعدی: <span className="font-medium text-text">{next.title}</span></> : null}</p>
        </div>
        {next ? (
          <Link href={next.href} className="hidden h-10 shrink-0 items-center gap-1.5 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand shadow-[var(--shadow-sm)] hover:bg-brand-strong sm:inline-flex">
            ادامه<ArrowUpLeft className="size-4" aria-hidden="true" />
          </Link>
        ) : null}
        <button type="button" onClick={toggle} aria-expanded={open} aria-controls="setup-steps" aria-label={open ? 'جمع کردن مراحل' : 'نمایش مراحل'}
          className="flex size-9 shrink-0 items-center justify-center rounded-lg text-text-subtle hover:bg-surface-muted">
          <ChevronDown className={cx('size-5 transition-transform', open && 'rotate-180')} aria-hidden="true" />
        </button>
      </div>

      {open ? (
        <ul id="setup-steps" className="grid gap-2 border-t border-brand/10 px-5 py-4 sm:grid-cols-2 lg:grid-cols-3">
          {data.steps.map((step) => (
            <li key={step.key} className="group relative">
              {step.done ? (
                <div className="flex items-start gap-3 rounded-xl px-3 py-2.5 text-text-muted">
                  <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-success text-white" aria-hidden="true"><Check className="size-3" strokeWidth={3} /></span>
                  <span className="text-sm line-through decoration-text-subtle/50">{step.title}</span>
                </div>
              ) : (
                <>
                  <Link href={step.href} className={cx(
                    'flex items-start gap-3 rounded-xl bg-surface px-3 py-2.5 pe-9 shadow-[var(--shadow-sm)] ring-1 transition-shadow hover:shadow-[var(--shadow-md)]',
                    step.key === next?.key ? 'ring-brand/40' : 'ring-border',
                  )}>
                    <span className={cx('mt-0.5 size-5 shrink-0 rounded-full border-2', step.key === next?.key ? 'border-brand' : 'border-border-strong')} aria-hidden="true" />
                    <span>
                      <span className="flex items-center gap-1.5 text-sm font-medium">
                        {step.title}
                        {step.essential ? <Sparkles className="size-3.5 text-accent" aria-label="ضروری" /> : null}
                      </span>
                      <span className="block text-xs leading-5 text-text-muted">{step.hint}</span>
                    </span>
                  </Link>
                  {!step.essential && canSkip ? (
                    <button type="button" disabled={pending} onClick={() => start(async () => { const updated = await skipSetupStep(step.key, true); if (updated) setData(updated); })}
                      aria-label={`رد کردن «${step.title}»`} title="لازم ندارم"
                      className="absolute end-2 top-2 flex size-7 items-center justify-center rounded-lg text-text-subtle opacity-60 hover:bg-surface-muted hover:opacity-100 focus-visible:opacity-100">
                      <EyeOff className="size-3.5" />
                    </button>
                  ) : null}
                </>
              )}
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  );
}
