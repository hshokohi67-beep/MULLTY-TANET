'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { Check, ChevronDown, Rocket } from 'lucide-react';
import { formatNumber } from '@cafe/locale';

export interface SetupStep { done: boolean; title: string; hint: string; href: string }

/** First-run checklist. Stays visible until complete; can be folded away meanwhile (remembered). */
export function SetupChecklist({ steps }: { steps: SetupStep[] }) {
  const done = steps.filter((s) => s.done).length;
  const [open, setOpen] = useState(true);

  useEffect(() => {
    const timer = setTimeout(() => {
      try {
        setOpen(localStorage.getItem('setup-folded') !== '1');
      } catch {
        // storage unavailable
      }
    }, 0);

    return () => clearTimeout(timer);
  }, []);

  if (done === steps.length) return null;

  const toggle = () => {
    setOpen((o) => {
      try {
        localStorage.setItem('setup-folded', o ? '1' : '0');
      } catch {
        // storage unavailable
      }
      return !o;
    });
  };

  return (
    <section className="overflow-hidden rounded-xl border border-brand/25 bg-gradient-to-l from-brand-soft/60 to-surface shadow-[var(--shadow-sm)]">
      <button type="button" onClick={toggle} aria-expanded={open} className="flex w-full items-center gap-4 px-5 py-4 text-start">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand text-on-brand" aria-hidden="true"><Rocket className="size-5" /></span>
        <span className="flex-1">
          <span className="block font-semibold">راه‌اندازی کافه</span>
          <span className="block text-sm text-text-muted">{formatNumber(done)} از {formatNumber(steps.length)} مرحله انجام شده</span>
        </span>
        <span className="hidden h-2 w-32 overflow-hidden rounded-full bg-surface-muted sm:block" role="progressbar" aria-valuemin={0} aria-valuemax={steps.length} aria-valuenow={done} aria-label="پیشرفت راه‌اندازی">
          <span className="block h-full rounded-full bg-brand transition-[width] duration-[var(--duration-slow)]" style={{ width: `${(done / steps.length) * 100}%` }} />
        </span>
        <ChevronDown className={`size-5 text-text-subtle transition-transform ${open ? 'rotate-180' : ''}`} aria-hidden="true" />
      </button>
      {open ? (
        <ul className="grid gap-2 border-t border-brand/15 px-5 py-4 sm:grid-cols-2 lg:grid-cols-3">
          {steps.map((step) => (
            <li key={step.title}>
              {step.done ? (
                <div className="flex items-start gap-3 rounded-lg px-3 py-2.5 text-text-muted">
                  <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-success text-white" aria-hidden="true"><Check className="size-3" /></span>
                  <span className="text-sm line-through">{step.title}</span>
                </div>
              ) : (
                <Link href={step.href} className="flex items-start gap-3 rounded-lg bg-surface px-3 py-2.5 shadow-[var(--shadow-sm)] transition-shadow hover:shadow-[var(--shadow-md)]">
                  <span className="mt-0.5 size-5 shrink-0 rounded-full border-2 border-border-strong" aria-hidden="true" />
                  <span>
                    <span className="block text-sm font-medium">{step.title}</span>
                    <span className="block text-xs text-text-muted">{step.hint}</span>
                  </span>
                </Link>
              )}
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  );
}
