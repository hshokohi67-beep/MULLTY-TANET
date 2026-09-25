'use client';

import Link from 'next/link';
import { useState, useTransition } from 'react';
import { CalendarDays, Check, ExternalLink, Megaphone, Pause, Play, X } from 'lucide-react';
import { Badge, Button, Card, CardHeader, Checkbox, Dialog, EmptyState, TextField, cx } from '@cafe/ui';
import { addDays, formatJalaliDate, formatMoney, formatNumber, toPersianDigits } from '@cafe/locale';
import { reviewAd, updateAdPlacement } from '@/app/actions/platform';
import { CoverArt } from '@/components/explore/Art';
import { MoneyField } from '@/components/MoneyField';
import { campaignState, type Placement, type PlatformCampaign } from '@/lib/ads-types';

const TABS: { key: string | null; label: string }[] = [
  { key: 'pending', label: 'در انتظار بررسی' }, { key: 'issues', label: 'پرداخت نیازمند بررسی' }, { key: 'paid', label: 'پرداخت‌شده' },
  { key: 'approved', label: 'منتظر پرداخت' }, { key: 'rejected', label: 'ردشده' }, { key: 'suspended', label: 'متوقف' }, { key: null, label: 'همه' },
];

const ISSUES: Record<string, string> = {
  state: 'کمپین هنگام پرداخت در وضعیت تأییدشده نبود.',
  amount: 'مبلغ پرداختی با قیمت کمپین نمی‌خواند.',
  capacity: 'جایگاه هنگام پرداخت پر شده بود؛ تاریخ را با کافه هماهنگ کنید.',
};

export function AdsReview({ campaigns, counts, placements, status }: { campaigns: PlatformCampaign[]; counts: Record<string, number>; placements: Placement[]; status: string | null }) {
  const [reasonFor, setReasonFor] = useState<{ c: PlatformCampaign; action: 'reject' | 'suspend' } | null>(null);
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [pending, start] = useTransition();
  const placementName = (key: string) => placements.find((p) => p.key === key)?.name ?? key;
  const act = (c: PlatformCampaign, action: 'approve' | 'reject' | 'suspend' | 'resume', why: string | null = null) => start(async () => {
    const r = await reviewAd(c.id, action, why);
    if (r.ok) { setReasonFor(null); setReason(''); setError(null); } else setError(r.message);
  });

  return (
    <div className="flex flex-col gap-6">
      <nav aria-label="وضعیت" className="flex flex-wrap gap-2">
        {TABS.map((t) => {
          const n = t.key === null ? null : counts[t.key] ?? 0;
          const on = status === t.key;

          return (
            <Link key={t.label} href={t.key ? `/platform/ads?status=${t.key}` : '/platform/ads'} aria-current={on ? 'true' : undefined}
              className={cx('inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm', on ? 'border-brand bg-brand text-on-brand' : 'border-border bg-surface text-text-muted hover:text-text')}>
              {t.label}{n ? <span className={cx('rounded-full px-1.5 text-xs', on ? 'bg-on-brand/20' : 'bg-surface-muted')}>{toPersianDigits(n)}</span> : null}
            </Link>
          );
        })}
      </nav>
      {error && !reasonFor ? <p role="alert" className="text-sm text-danger">{error}</p> : null}

      {campaigns.length === 0 ? (
        <Card><EmptyState icon={<Megaphone />} title={status === 'pending' ? 'کمپینی در انتظار بررسی نیست' : 'کمپینی در این بخش نیست'} /></Card>
      ) : (
        <ul className="grid gap-4 lg:grid-cols-2">
          {campaigns.map((c) => {
            const state = campaignState(c);
            const img = c.image_small_url ?? c.image_url;

            return (
              <li key={c.id} className="flex flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-[var(--shadow-sm)]">
                <div className="relative aspect-[16/6] overflow-hidden">
                  {img
                    // eslint-disable-next-line @next/next/no-img-element -- ad creative from object storage
                    ? <img src={img} alt="" className="size-full object-cover" />
                    : <CoverArt store={c.tenant.slug ?? c.id} name={c.tenant.name ?? '؟'} />}
                  <div className="absolute inset-0 bg-gradient-to-l from-scrim to-transparent" aria-hidden="true" />
                  <div className="absolute inset-y-0 start-0 flex max-w-[75%] flex-col justify-center gap-1 p-4 text-on-media">
                    <p className="text-xs text-on-media-muted">{c.tenant.name}</p>
                    <p className="text-lg font-black leading-tight">{c.headline}</p>
                    {c.body ? <p className="line-clamp-1 text-xs text-on-media-muted">{c.body}</p> : null}
                    <span className="mt-1 w-fit rounded-lg bg-on-media/20 px-2 py-0.5 text-xs">{c.cta_label}</span>
                  </div>
                </div>
                <div className="flex flex-1 flex-col gap-2 p-4 text-sm">
                  <div className="flex flex-wrap items-center gap-2"><span className="font-bold">{c.name}</span><Badge tone={state.tone} dot>{state.label}</Badge></div>
                  <p className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-text-muted">
                    <span>{placementName(c.placement)}</span>
                    <span className="inline-flex items-center gap-1"><CalendarDays className="size-3.5" aria-hidden="true" />{formatJalaliDate(c.start_date)} تا {formatJalaliDate(addDays(c.start_date, c.days - 1))}</span>
                    <span>{c.cities.length ? c.cities.join('، ') : 'همه‌ی شهرها'}</span>
                    <span className="font-semibold text-text">{formatMoney(c.amount)}</span>
                    {c.paid_at ? <span className="text-success">پرداخت {formatJalaliDate(c.paid_at)}</span> : null}
                  </p>
                  {c.status === 'paid' || c.impressions ? <p className="text-xs text-text-muted">{formatNumber(c.impressions)} نمایش • {formatNumber(c.clicks)} کلیک</p> : null}
                  {c.payment_issue ? <p className="rounded-lg bg-warning-soft px-3 py-2 text-xs text-warning">{ISSUES[c.payment_issue] ?? c.payment_issue}</p> : null}
                  {c.review_note ? <p className="rounded-lg bg-surface-muted px-3 py-2 text-xs">یادداشت: {c.review_note}</p> : null}
                  <div className="mt-auto flex flex-wrap gap-2 pt-2">
                    {c.status === 'pending' ? (
                      <>
                        <Button size="sm" icon={<Check />} loading={pending} onClick={() => act(c, 'approve')}>تأیید</Button>
                        <Button size="sm" variant="secondary" icon={<X />} onClick={() => setReasonFor({ c, action: 'reject' })}>رد با دلیل</Button>
                      </>
                    ) : null}
                    {c.status === 'paid' ? <Button size="sm" variant="secondary" icon={<Pause />} onClick={() => setReasonFor({ c, action: 'suspend' })}>توقف نمایش</Button> : null}
                    {c.status === 'suspended' ? <Button size="sm" icon={<Play />} loading={pending} onClick={() => act(c, 'resume')}>ادامه‌ی نمایش</Button> : null}
                    {c.tenant.slug ? <Link href={`/explore/${c.tenant.slug}`} target="_blank" className="inline-flex h-8 items-center gap-1 rounded-lg px-2.5 text-sm text-brand hover:bg-brand-soft"><ExternalLink className="size-4" aria-hidden="true" />صفحه‌ی کافه</Link> : null}
                  </div>
                </div>
              </li>
            );
          })}
        </ul>
      )}

      <Card>
        <CardHeader title="جایگاه‌ها و قیمت" description="قیمت روزانه (تومان) و تعداد کمپین همزمان. تغییر قیمت فقط روی کمپین‌هایی که از این به بعد ذخیره می‌شوند اثر دارد." />
        <div className="grid gap-4 p-5 md:grid-cols-2">{placements.map((p) => <PlacementEditor key={p.key} p={p} />)}</div>
      </Card>

      <Dialog open={reasonFor !== null} onClose={() => setReasonFor(null)} size="sm" title={reasonFor ? `${reasonFor.action === 'reject' ? 'رد کمپین' : 'توقف نمایش'} • ${reasonFor.c.name}` : ''}>
        {reasonFor ? (
          <form className="flex flex-col gap-4" onSubmit={(e) => { e.preventDefault(); act(reasonFor.c, reasonFor.action, reason); }}>
            <TextField label="دلیل (به کافه‌دار نشان داده می‌شود)" value={reason} onChange={(e) => setReason(e.target.value)} required maxLength={200} />
            {reasonFor.c.paid_at && reasonFor.action === 'reject' ? <p className="text-xs text-warning">این کمپین پرداخت شده است؛ بازگرداندن وجه را جداگانه انجام دهید.</p> : null}
            {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
            <Button type="submit" variant="danger" loading={pending}>{reasonFor.action === 'reject' ? 'رد کمپین' : 'توقف نمایش'}</Button>
          </form>
        ) : null}
      </Dialog>
    </div>
  );
}

function PlacementEditor({ p }: { p: Placement }) {
  const [active, setActive] = useState(p.is_active);
  const [msg, setMsg] = useState<string | null>(null);
  const [pending, start] = useTransition();

  return (
    <form className="flex flex-col gap-3 rounded-xl border border-border p-4" onSubmit={(e) => {
      e.preventDefault();
      const f = new FormData(e.currentTarget);
      const toman = Number(String(f.get('price') ?? '').replace(/[^\d]/g, ''));
      start(async () => {
        const r = await updateAdPlacement(p.key, toman * 10, Number(f.get('capacity')), active);
        setMsg(r.ok ? 'ذخیره شد.' : r.message);
      });
    }}>
      <div>
        <p className="font-semibold">{p.name}</p>
        <p className="text-xs text-text-muted">{p.description}</p>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <MoneyField label="قیمت روزانه (تومان)" name="price" defaultValue={String(p.daily_price / 10)} required />
        <TextField label="کمپین همزمان" name="capacity" type="number" min={1} max={50} defaultValue={p.capacity} required />
      </div>
      <Checkbox label="فعال (قابل رزرو)" checked={active} onChange={(e) => setActive(e.target.checked)} />
      <div className="flex items-center gap-3">
        <Button type="submit" size="sm" loading={pending}>ذخیره</Button>
        {msg ? <span role="status" className="text-xs text-text-muted">{msg}</span> : null}
      </div>
    </form>
  );
}
