'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import { CalendarRange, Check, GitCompareArrows, Store } from 'lucide-react';
import { Button, cx } from '@cafe/ui';
import { JalaliDateField } from '@/components/JalaliDateField';
import type { Preset } from '@/lib/report-types';

type State = { from: string; to: string; compare: string; branch: string; tab: string };

function url(s: State): string {
  return `/dashboard/reports?${new URLSearchParams(Object.entries(s).filter(([, v]) => v !== ''))}`;
}

/** Period presets, a custom Jalali range, branch and comparison. Everything lives in the URL. */
export function ReportFilters({ presets, from, to, today, compare, branch, branches, tab }: {
  presets: Preset[]; from: string; to: string; today: string; compare: string; branch: string; branches: { id: string; name: string }[]; tab: string;
}) {
  const router = useRouter();
  const [pending, start] = useTransition();
  const [custom, setCustom] = useState(false);
  const state: State = { from, to, compare, branch, tab };
  const active = presets.find((p) => p.from === from && p.to === to)?.key;
  const go = (patch: Partial<State>) => start(() => router.push(url({ ...state, ...patch })));
  const select = 'h-9 rounded-lg border border-border bg-surface ps-8 pe-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none';

  return (
    <div className={cx('flex flex-col gap-3 rounded-2xl border border-border bg-surface p-3 shadow-[var(--shadow-sm)] transition-opacity', pending && 'opacity-60')} aria-busy={pending}>
      <div className="flex flex-wrap items-center gap-1.5">
        {presets.map((p) => (
          <Link key={p.key} href={url({ ...state, from: p.from, to: p.to })} aria-current={active === p.key ? 'true' : undefined}
            className={cx('rounded-full border px-3 py-1.5 text-sm transition-colors', active === p.key ? 'border-brand bg-brand-soft font-semibold text-brand-strong' : 'border-transparent text-text-muted hover:bg-surface-muted hover:text-text')}>
            {p.label}
          </Link>
        ))}
        <button type="button" onClick={() => setCustom((v) => !v)} aria-expanded={custom}
          className={cx('inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm', !active || custom ? 'border-brand bg-brand-soft font-semibold text-brand-strong' : 'border-transparent text-text-muted hover:bg-surface-muted hover:text-text')}>
          <CalendarRange className="size-4" aria-hidden="true" />بازه‌ی دلخواه
        </button>

        <div className="ms-auto flex flex-wrap items-center gap-2">
          {branches.length > 1 ? (
            <label className="relative">
              <span className="sr-only">شعبه</span>
              <Store className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-text-subtle" aria-hidden="true" />
              <select value={branch} onChange={(e) => go({ branch: e.target.value })} className={select}>
                <option value="">همه‌ی شعبه‌ها</option>
                {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
              </select>
            </label>
          ) : null}
          <label className="relative">
            <span className="sr-only">مقایسه با</span>
            <GitCompareArrows className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-text-subtle" aria-hidden="true" />
            <select value={compare} onChange={(e) => go({ compare: e.target.value })} className={select}>
              <option value="previous">مقایسه با دوره‌ی قبل</option>
              <option value="last_year">مقایسه با پارسال</option>
              <option value="none">بدون مقایسه</option>
            </select>
          </label>
        </div>
      </div>

      {custom ? (
        <form action="/dashboard/reports" method="get" className="flex flex-wrap items-end gap-4 border-t border-border pt-3"
          onSubmit={(e) => {
            e.preventDefault();
            const fd = new FormData(e.currentTarget);
            let f = String(fd.get('from'));
            let t = String(fd.get('to'));
            if (f > t) [f, t] = [t, f];
            if (t > today) t = today;
            setCustom(false);
            go({ from: f, to: t });
          }}>
          <JalaliDateField label="از" name="from" defaultValue={from} years={3} />
          <JalaliDateField label="تا" name="to" defaultValue={to} years={3} />
          <Button type="submit" icon={<Check />} loading={pending}>نمایش</Button>
          <p className="w-full text-xs text-text-subtle">حداکثر ۴۰۰ روز؛ بالای ۶۲ روز نمودار ماه‌به‌ماه نمایش داده می‌شود.</p>
        </form>
      ) : null}
    </div>
  );
}
