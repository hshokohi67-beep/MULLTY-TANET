'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';
import { BookOpen, Lock, Search, SearchX, Sparkles, X } from 'lucide-react';
import { cx } from '@cafe/ui';
import { normalizeForSearch, toPersianDigits } from '@cafe/locale';
import { GROUP_TITLES, type HelpGroup, type HelpIcon as IconKey } from '@/lib/help/types';
import { HelpIcon } from './HelpIcon';

export interface IndexTopic { key: string; title: string; summary: string; icon: IconKey; group: HelpGroup; locked: boolean; text: string; isRole: boolean }

/**
 * The help centre's front page: who you are here, your role guide first, then every topic you can
 * open, grouped like the sidebar, with a search over titles, steps, examples and FAQs.
 */
export function HelpIndex({ topics, base, roleNames, isOwner, intro }: { topics: IndexTopic[]; base: string; roleNames: string[]; isOwner: boolean; intro: string }) {
  const [q, setQ] = useState('');
  const needle = normalizeForSearch(q.trim());
  const found = useMemo(() => (needle ? topics.filter((t) => normalizeForSearch(t.text).includes(needle)) : topics), [needle, topics]);
  // Role guides get their own row above; in the grid they appear only as search results.
  const listed = found.filter((t) => needle || !t.isRole);
  const groups = (Object.keys(GROUP_TITLES) as HelpGroup[]).map((g) => ({ g, items: listed.filter((t) => t.group === g) })).filter((x) => x.items.length > 0);
  const roleGuides = topics.filter((t) => t.isRole);

  return (
    <div className="flex flex-col gap-8">
      <header className="relative overflow-hidden rounded-3xl bg-brand-strong p-6 text-on-brand shadow-[var(--shadow-lg)] sm:p-8">
        <div aria-hidden="true" className="absolute inset-0 bg-gradient-to-br from-brand to-brand-strong" />
        <div aria-hidden="true" className="absolute -end-16 -top-24 size-80 rounded-full bg-on-brand/10 blur-3xl" />
        <div className="relative flex flex-col gap-4">
          <span className="inline-flex w-fit items-center gap-2 rounded-full bg-on-brand/15 px-3 py-1 text-xs font-semibold"><BookOpen className="size-3.5" aria-hidden="true" />راهنمای کامل کافه‌یار</span>
          <h1 className="text-2xl font-black leading-tight sm:text-4xl">هر بخش، قدم‌به‌قدم و با مثال</h1>
          <p className="max-w-2xl leading-8 opacity-90">{intro}</p>
          {roleNames.length ? (
            <p className="flex flex-wrap items-center gap-2 text-sm">
              <span className="opacity-85">نقش شما:</span>
              {roleNames.map((r) => <span key={r} className="rounded-full bg-on-brand px-3 py-1 text-xs font-bold text-brand-strong">{r}</span>)}
              <span className="opacity-85">• {toPersianDigits(topics.length)} موضوع برای شما</span>
            </p>
          ) : null}
          <label className="relative mt-2 block max-w-xl">
            <span className="sr-only">جست‌وجو در راهنما</span>
            <Search className="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-text-subtle" aria-hidden="true" />
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="مثلاً «بازگشت وجه»، «QR»، «شیفت»، «کش‌بک»…"
              className="h-12 w-full rounded-2xl border-0 bg-surface ps-12 pe-10 text-text shadow-[var(--shadow-md)] placeholder:text-text-subtle focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
            {q ? <button type="button" onClick={() => setQ('')} aria-label="پاک کردن" className="absolute end-2 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-full text-text-muted hover:bg-surface-muted"><X className="size-4" /></button> : null}
          </label>
        </div>
      </header>

      {!needle && roleGuides.length ? (
        <section aria-labelledby="roles" className="flex flex-col gap-3">
          <h2 id="roles" className="flex items-center gap-2 text-lg font-black"><Sparkles className="size-5 text-accent" aria-hidden="true" />{isOwner ? 'راهنمای شروع هر نقش (برای آموزش تیم)' : 'از اینجا شروع کنید'}</h2>
          <div className={cx('grid gap-3', roleGuides.length > 1 ? 'sm:grid-cols-2 lg:grid-cols-3' : '')}>
            {roleGuides.map((t) => (
              <Link key={t.key} href={`${base}/${t.key}`} className="group flex items-start gap-3 rounded-2xl border border-accent/30 bg-accent-soft p-4 transition-shadow hover:shadow-[var(--shadow-md)]">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-accent text-on-brand"><HelpIcon name={t.icon} className="size-5" /></span>
                <span className="min-w-0"><span className="block font-bold">{t.title}</span><span className="line-clamp-2 text-sm text-text-muted">{t.summary}</span></span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      {listed.length === 0 && (needle || roleGuides.length === 0) ? (
        <div className="flex flex-col items-center gap-3 rounded-3xl border border-dashed border-border bg-surface px-6 py-14 text-center">
          <span className="flex size-14 items-center justify-center rounded-2xl bg-surface-muted text-text-subtle"><SearchX className="size-7" aria-hidden="true" /></span>
          <p className="font-semibold">موضوعی با «{q}» پیدا نشد</p>
          <p className="text-sm text-text-muted">کلمه‌ی کوتاه‌تر یا نام بخش را امتحان کنید.</p>
        </div>
      ) : groups.map(({ g, items }) => (
        <section key={g} aria-labelledby={`g-${g}`} className="flex flex-col gap-3">
          <h2 id={`g-${g}`} className="text-lg font-black">{GROUP_TITLES[g]}</h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {items.map((t) => (
              <Link key={t.key} href={`${base}/${t.key}`} className="group flex items-start gap-3 rounded-2xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)] transition-[box-shadow,border-color] hover:border-brand hover:shadow-[var(--shadow-md)]">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand transition-colors group-hover:bg-brand group-hover:text-on-brand"><HelpIcon name={t.icon} className="size-5" /></span>
                <span className="min-w-0">
                  <span className="flex items-center gap-1.5 font-bold">{t.title}{t.locked ? <Lock className="size-3.5 text-warning" aria-label="در پلن فعلی فعال نیست" /> : null}</span>
                  <span className="line-clamp-2 text-sm text-text-muted">{t.summary}</span>
                </span>
              </Link>
            ))}
          </div>
        </section>
      ))}
    </div>
  );
}
