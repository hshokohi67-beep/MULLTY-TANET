'use client';

import Link from 'next/link';
import { useState, useTransition } from 'react';
import { CalendarCheck, CalendarDays, Check, Copy, HeartCrack, LayoutGrid, Lightbulb, Tags, Undo2, X } from 'lucide-react';
import { Badge, Button, cx } from '@cafe/ui';
import { formatJalaliLong, toPersianDigits } from '@cafe/locale';
import { setOccasionState } from '@/app/actions/occasions';
import { collectionHref, discountHref, occasionWhen, type UpcomingOccasion } from '@/lib/occasion-types';

const day = (d: string) => new Date(`${d}T12:00:00Z`);

const TONE: Record<UpcomingOccasion['tone'], { band: string; chip: string }> = {
  festive: { band: 'bg-accent', chip: 'bg-accent-soft text-accent' },
  calm: { band: 'bg-info', chip: 'bg-info-soft text-info' },
  mourning: { band: 'bg-text-subtle', chip: 'bg-surface-muted text-text-muted' },
};

const STATUS_LABEL: Record<NonNullable<UpcomingOccasion['status']>, string> = { planned: 'برنامه دارم', dismissed: 'رد شد', done: 'انجام شد' };

/**
 * One occasion: when, what it means for a café, ready ideas, and one-click actions (a discount or a
 * menu collection prefilled with its dates). Mourning days get reminders only, never promotions.
 */
export function OccasionCard({ occasion: o, canDiscounts, canCatalog, compact = false }: {
  occasion: UpcomingOccasion;
  canDiscounts: boolean;
  canCatalog: boolean;
  compact?: boolean;
}) {
  const [status, setStatus] = useState(o.status);
  const [copied, setCopied] = useState(false);
  const [pending, start] = useTransition();
  const tone = TONE[o.tone];
  const mourning = o.tone === 'mourning';
  const multiDay = o.starts_on !== o.ends_on;

  const answer = (next: UpcomingOccasion['status']) => start(async () => {
    const r = await setOccasionState(o.id, next);
    if (r.ok) setStatus(next);
  });

  const copy = async () => {
    if (!o.campaign_text) return;
    try { await navigator.clipboard.writeText(o.campaign_text); setCopied(true); setTimeout(() => setCopied(false), 2000); } catch { /* clipboard blocked: the text stays visible */ }
  };

  return (
    <article id={`o-${o.id}`} className={cx('relative flex overflow-hidden rounded-3xl border border-border bg-surface', status === 'dismissed' && 'opacity-60')}>
      <span aria-hidden="true" className={cx('w-1.5 shrink-0', tone.band)} />
      <div className="flex min-w-0 flex-1 flex-col gap-3 p-4 sm:p-5">
        <header className="flex flex-wrap items-start gap-3">
          <div className="flex size-14 shrink-0 flex-col items-center justify-center rounded-2xl bg-surface-muted text-center leading-tight">
            <span className="text-lg font-black tabular">{toPersianDigits(formatJalaliLong(day(o.starts_on)).split(' ')[0])}</span>
            <span className="text-[11px] text-text-muted">{formatJalaliLong(day(o.starts_on)).split(' ')[1]}</span>
          </div>
          <div className="min-w-0 flex-1">
            <h3 className="flex flex-wrap items-center gap-2 text-base font-bold">
              {mourning ? <HeartCrack className="size-4 text-text-muted" aria-hidden="true" /> : null}
              {o.title}
              <span className={cx('rounded-full px-2 py-0.5 text-[11px] font-semibold', tone.chip)}>{occasionWhen(o, (n) => toPersianDigits(n))}</span>
            </h3>
            <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-text-muted">
              <CalendarDays className="size-3.5" aria-hidden="true" />
              {formatJalaliLong(day(o.starts_on), undefined, true)}
              {multiDay ? <> تا {formatJalaliLong(day(o.ends_on))}</> : null}
              <span aria-hidden="true">•</span>{o.kind_label}
              {o.is_holiday ? <Badge tone="info">تعطیل رسمی</Badge> : null}
              {o.approximate ? <Badge tone="warning">تاریخ تقریبی</Badge> : null}
              {status ? <Badge tone={status === 'dismissed' ? 'neutral' : 'success'}>{STATUS_LABEL[status]}</Badge> : null}
            </p>
          </div>
        </header>

        {o.description && !compact ? <p className="text-sm leading-7 text-text-muted">{o.description}</p> : null}

        {o.ideas.length && !compact ? (
          <div className="rounded-2xl bg-surface-muted p-3.5">
            <p className="mb-1.5 flex items-center gap-1.5 text-sm font-semibold"><Lightbulb className="size-4 text-warning" aria-hidden="true" />{mourning ? 'یادآوری' : 'ایده‌ها'}</p>
            <ul className="list-inside list-disc space-y-1 text-sm text-text-muted marker:text-text-subtle">
              {o.ideas.map((idea) => <li key={idea}>{idea}</li>)}
            </ul>
          </div>
        ) : null}

        {o.campaign_text && !compact ? (
          <div className="flex items-start gap-2 rounded-2xl border border-dashed border-border-strong p-3 text-sm">
            <p className="flex-1 leading-7">«{o.campaign_text}»</p>
            <button type="button" onClick={copy} aria-label="کپی متن" className="flex size-8 shrink-0 items-center justify-center rounded-full text-text-muted hover:bg-surface-muted">
              {copied ? <Check className="size-4 text-success" /> : <Copy className="size-4" />}
            </button>
          </div>
        ) : null}

        <div className="flex flex-wrap items-center gap-2">
          {!mourning && canDiscounts ? (
            <Link href={discountHref(o)} className="inline-flex h-9 items-center gap-1.5 rounded-xl bg-brand px-3 text-sm font-semibold text-on-brand hover:bg-brand-strong">
              <Tags className="size-4" aria-hidden="true" />ساخت تخفیف
            </Link>
          ) : null}
          {!mourning && canCatalog ? (
            <Link href={collectionHref(o)} className="inline-flex h-9 items-center gap-1.5 rounded-xl border border-border px-3 text-sm hover:bg-surface-muted">
              <LayoutGrid className="size-4" aria-hidden="true" />منوی مناسبتی
            </Link>
          ) : null}
          {status === null ? (
            <>
              <Button size="sm" variant="secondary" loading={pending} icon={<CalendarCheck className="size-4" />} onClick={() => answer('planned')}>برنامه دارم</Button>
              <Button size="sm" variant="ghost" icon={<X className="size-4" />} onClick={() => answer('dismissed')}>رد کن</Button>
            </>
          ) : (
            <>
              {status === 'planned' ? <Button size="sm" variant="secondary" loading={pending} icon={<Check className="size-4" />} onClick={() => answer('done')}>انجام شد</Button> : null}
              <Button size="sm" variant="ghost" icon={<Undo2 className="size-4" />} onClick={() => answer(null)}>برگرداندن</Button>
            </>
          )}
        </div>
      </div>
    </article>
  );
}
