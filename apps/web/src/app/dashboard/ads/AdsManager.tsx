'use client';

import Link from 'next/link';
import { useEffect, useMemo, useRef, useState, useTransition } from 'react';
import {
  ArrowLeft, BadgePercent, CalendarDays, CircleDollarSign, Eye, ImagePlus, LayoutTemplate, Megaphone, MousePointerClick, Pencil, Plus, Rocket, Search, Send,
  Trash2, TrendingUp, X,
} from 'lucide-react';
import { Alert, Badge, Button, Card, CardHeader, ColumnChart, Dialog, EmptyState, SelectField, StatTile, TextField, cx } from '@cafe/ui';
import { addDays, formatJalaliDate, formatMoney, formatNumber, toPersianDigits, todayIn } from '@cafe/locale';
import { campaignAction, payCampaign, quoteCampaign, saveCampaign, uploadCampaignImage } from '@/app/actions/ads';
import { JalaliDateField } from '@/components/JalaliDateField';
import { CoverArt, Logo } from '@/components/explore/Art';
import { BrandStyles, StoreCard } from '@/components/explore/ExploreParts';
import { campaignState, type AdQuote, type AdsOverview, type Campaign, type CampaignInput } from '@/lib/ads-types';
import type { StoreCard as Card_ } from '@/lib/marketplace-types';

interface Store { slug: string; name: string; logo_url: string | null; primary_color: string | null }

const DAY_CHOICES = [3, 7, 14, 30];

export function AdsManager({ data, store, listed, preview, payment }: { data: AdsOverview; store: Store; listed: boolean; preview: Card_ | null; payment: string | null }) {
  const [editing, setEditing] = useState<Campaign | 'new' | null>(null);
  const placementName = (key: string) => data.placements.find((p) => p.key === key)?.name ?? key;
  const s = data.summary;
  const chart = data.series.map((d) => ({ label: formatJalaliDate(d.date).split('/')[2] ?? '', value: d.impressions }));
  const hasTraffic = data.series.some((d) => d.impressions > 0);

  return (
    <div data-brand={store.slug} className="flex flex-col gap-6">
      <BrandStyles stores={[{ store: store.slug, primary_color: store.primary_color }]} />
      {payment === 'ok' ? <Alert tone="success" title="پرداخت انجام شد">کمپین در روز شروع خودش در کافه‌گردی نمایش داده می‌شود.</Alert> : null}
      {payment === 'failed' ? <Alert tone="danger" title="پرداخت انجام نشد">اگر مبلغی از حساب شما کم شده، طی ۷۲ ساعت برمی‌گردد. می‌توانید دوباره پرداخت کنید.</Alert> : null}
      {payment === 'cancelled' ? <Alert tone="warning">پرداخت لغو شد؛ کمپین هنوز منتظر پرداخت است.</Alert> : null}
      {!listed ? (
        <Alert tone="warning" title="کافه‌تان هنوز در کافه‌گردی نیست" action={<Link href="/dashboard/marketplace" className="text-sm font-semibold underline">تکمیل بازارگاه</Link>}>
          تبلیغ فقط برای کافه‌هایی نمایش داده می‌شود که در کافه‌گردی دیده می‌شوند.
        </Alert>
      ) : null}

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <StatTile label="نمایش (۳۰ روز)" value={formatNumber(s.impressions)} icon={<Eye />} trend={data.series.map((d) => d.impressions)} />
        <StatTile label="کلیک" value={formatNumber(s.clicks)} icon={<MousePointerClick />} trend={data.series.map((d) => d.clicks)} />
        <StatTile label="نرخ کلیک" value={s.ctr === null ? '—' : `٪${toPersianDigits(s.ctr)}`} icon={<TrendingUp />} hint="کلیک به ازای هر ۱۰۰ نمایش" />
        <StatTile label="هزینه‌ی ۳۰ روز" value={formatMoney(s.spend)} icon={<CircleDollarSign />} hint="بدون مالیات" />
        <StatTile label="کمپین در حال نمایش" value={toPersianDigits(s.live)} icon={<Megaphone />} hint={s.awaiting ? `${toPersianDigits(s.awaiting)} کمپین در انتظار` : 'کمپینی در انتظار نیست'} />
      </div>

      <Card>
        <CardHeader title="نمایش روزانه" description="چند بار کافه‌تان در جایگاه‌های تبلیغی دیده شده (هر بازدیدکننده حداکثر یک بار در ساعت)." />
        <div className="p-5">
          {hasTraffic ? <ColumnChart data={chart} format={(v) => formatNumber(v)} valueLabel="نمایش" caption="نمایش روزانه‌ی تبلیغ‌ها در ۳۰ روز اخیر" />
            : <p className="py-8 text-center text-sm text-text-muted">هنوز نمایشی ثبت نشده؛ با اولین کمپین، نمودار اینجا شکل می‌گیرد.</p>}
        </div>
      </Card>

      <section aria-labelledby="campaigns" className="flex flex-col gap-4">
        <div className="flex items-center justify-between gap-3">
          <h2 id="campaigns" className="text-lg font-bold">کمپین‌ها</h2>
          <Button icon={<Plus />} onClick={() => setEditing('new')}>کمپین تازه</Button>
        </div>

        {data.campaigns.length === 0 ? (
          <Card>
            <EmptyState icon={<Megaphone />} title="هنوز کمپینی نساخته‌اید" description="یک جایگاه انتخاب کنید، روزها را مشخص کنید و تیتر کوتاهی بنویسید. پیش از نمایش، کمپین بررسی می‌شود."
              action={<Button icon={<Plus />} onClick={() => setEditing('new')}>ساخت اولین کمپین</Button>} />
            <div className="grid gap-3 border-t border-border p-5 sm:grid-cols-2">
              {data.placements.map((p) => (
                <div key={p.key} className="flex items-start gap-3 rounded-xl bg-surface-muted p-4">
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand">{p.key === 'home_banner' ? <LayoutTemplate className="size-5" aria-hidden="true" /> : <Search className="size-5" aria-hidden="true" />}</span>
                  <span>
                    <span className="block font-semibold">{p.name}</span>
                    <span className="block text-sm text-text-muted">{p.description}</span>
                    <span className="mt-1 block text-sm font-semibold text-brand">روزی {formatMoney(p.daily_price)}</span>
                  </span>
                </div>
              ))}
            </div>
          </Card>
        ) : (
          <ul className="flex flex-col gap-3">
            {data.campaigns.map((c) => <CampaignRow key={c.id} c={c} store={store} placement={placementName(c.placement)} onEdit={() => setEditing(c)} />)}
          </ul>
        )}
      </section>

      {editing ? (
        <Builder key={editing === 'new' ? 'new' : editing.id} data={data} store={store} preview={preview} initial={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      ) : null}
    </div>
  );
}

function Thumb({ c, store, className }: { c: Pick<Campaign, 'image_small_url' | 'image_url'>; store: Store; className?: string }) {
  const url = c.image_small_url ?? c.image_url;

  return (
    <div className={cx('relative overflow-hidden rounded-xl', className)}>
      {url
        // eslint-disable-next-line @next/next/no-img-element -- ad creative from object storage
        ? <img src={url} alt="" className="size-full object-cover" />
        : <CoverArt store={store.slug} name={store.name} size="sm" />}
    </div>
  );
}

function CampaignRow({ c, store, placement, onEdit }: { c: Campaign; store: Store; placement: string; onEdit: () => void }) {
  const [pending, start] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const state = campaignState(c);
  const run = (fn: () => Promise<{ ok: boolean; message?: string }>) => start(async () => {
    const r = await fn();
    setError(r.ok ? null : (r.message ?? 'خطایی رخ داد.'));
  });
  const pay = () => start(async () => {
    const r = await payCampaign(c.id);
    if (r.ok) window.location.assign(r.data.redirect_url); else setError(r.message);
  });
  const end = addDays(c.start_date, c.days - 1);

  return (
    <li className="flex flex-col gap-4 rounded-2xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)] sm:flex-row sm:items-center">
      <Thumb c={c} store={store} className="aspect-[16/9] w-full shrink-0 sm:w-40" />
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <p className="font-bold">{c.name}</p>
          <Badge tone={state.tone} dot>{state.label}</Badge>
        </div>
        <p className="mt-0.5 line-clamp-1 text-sm text-text-muted">«{c.headline}»</p>
        <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-muted">
          <span className="inline-flex items-center gap-1">{c.placement === 'home_banner' ? <LayoutTemplate className="size-3.5" aria-hidden="true" /> : <Search className="size-3.5" aria-hidden="true" />}{placement}</span>
          <span className="inline-flex items-center gap-1"><CalendarDays className="size-3.5" aria-hidden="true" />{formatJalaliDate(c.start_date)} تا {formatJalaliDate(end)} ({toPersianDigits(c.days)} روز)</span>
          <span>{c.cities.length ? c.cities.join('، ') : 'همه‌ی شهرها'}</span>
          <span className="font-semibold text-text">{formatMoney(c.amount)}</span>
        </p>
        {c.status === 'paid' || c.impressions > 0 ? (
          <p className="mt-2 flex flex-wrap gap-3 text-xs">
            <span><b className="text-text">{formatNumber(c.impressions)}</b> نمایش</span>
            <span><b className="text-text">{formatNumber(c.clicks)}</b> کلیک</span>
            {c.ctr !== null ? <span>نرخ کلیک <b className="text-text">٪{toPersianDigits(c.ctr)}</b></span> : null}
          </p>
        ) : null}
        {c.review_note && (c.status === 'rejected' || c.status === 'suspended') ? <p className="mt-2 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">یادداشت بررسی: {c.review_note}</p> : null}
        {c.payment_issue ? <p className="mt-2 rounded-lg bg-warning-soft px-3 py-2 text-sm text-warning">پرداخت شما ثبت شده و کمپین برای هماهنگی دوباره بررسی می‌شود.</p> : null}
        {error ? <p role="alert" className="mt-2 text-sm text-danger">{error}</p> : null}
      </div>
      <div className="flex flex-wrap gap-2 sm:flex-col sm:items-stretch">
        {c.status === 'approved' ? <Button icon={<Rocket />} loading={pending} onClick={pay}>پرداخت و شروع</Button> : null}
        {c.status === 'draft' || c.status === 'rejected' ? <Button icon={<Send />} loading={pending} onClick={() => run(() => campaignAction(c.id, 'submit'))}>ارسال برای بررسی</Button> : null}
        {c.editable ? <Button variant="secondary" icon={<Pencil />} onClick={onEdit}>ویرایش</Button> : null}
        {c.editable ? <Button variant="ghost" icon={<X />} loading={pending} onClick={() => run(() => campaignAction(c.id, 'cancel'))}>لغو</Button> : null}
      </div>
    </li>
  );
}

/* ------------------------------------------------------------------ builder */

function Builder({ data, store, preview, initial, onClose }: { data: AdsOverview; store: Store; preview: Card_ | null; initial: Campaign | null; onClose: () => void }) {
  const firstPlacement = data.placements[0]?.key ?? 'search_top';
  const [id, setId] = useState<string | null>(initial?.id ?? null);
  const [campaign, setCampaign] = useState<Campaign | null>(initial);
  const [input, setInput] = useState<CampaignInput>({
    name: initial?.name ?? '', placement: initial?.placement ?? firstPlacement, start_date: initial?.start_date ?? addDays(todayIn(), 1),
    days: initial?.days ?? 7, cities: initial?.cities ?? [], headline: initial?.headline ?? '', body: initial?.body ?? '', cta: initial?.cta ?? 'menu',
  });
  const [quote, setQuote] = useState<AdQuote | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [pending, start] = useTransition();
  const file = useRef<HTMLInputElement>(null);
  const placement = data.placements.find((p) => p.key === input.placement);
  const set = <K extends keyof CampaignInput>(k: K, v: CampaignInput[K]) => setInput((x) => ({ ...x, [k]: v }));

  useEffect(() => {
    let live = true;
    const t = setTimeout(async () => {
      const r = await quoteCampaign({ placement: input.placement, start_date: input.start_date, days: input.days }, id);
      if (live) setQuote(r.ok ? r.data : null);
    }, 250);

    return () => { live = false; clearTimeout(t); };
  }, [input.placement, input.start_date, input.days, id]);

  const save = async (): Promise<string | null> => {
    const r = await saveCampaign(id, input);
    if (!r.ok) { setErrors(r.errors ?? {}); setMessage(r.message); return null; }
    setErrors({}); setMessage(null); setId(r.data.id); setCampaign(r.data);

    return r.data.id;
  };
  const onSave = () => start(async () => { if (await save()) onClose(); });
  const onSubmit = () => start(async () => {
    const saved = await save();
    if (!saved) return;
    const r = await campaignAction(saved, 'submit');
    if (r.ok) onClose(); else setMessage(r.message);
  });
  const onImage = (f: File) => start(async () => {
    const saved = id ?? await save();
    if (!saved) return;
    const form = new FormData();
    form.append('image', f);
    const r = await uploadCampaignImage(saved, form);
    if (r.ok) setCampaign(r.data); else setMessage(r.errors?.image || r.message);
  });
  const removeImage = () => id && start(async () => { const r = await campaignAction(id, 'remove-image'); if (r.ok) setCampaign(r.data); });
  const ctaLabel = data.ctas.find((c) => c.key === input.cta)?.label ?? '';
  const needsImage = placement?.requires_image ?? false;
  const cardPreview = useMemo<Card_ | null>(() => (preview ? { ...preview, ad: { token: '', headline: input.headline || 'تیتر تبلیغ شما', body: input.body || null, cta_label: ctaLabel, href: '#' } } : null), [preview, input.headline, input.body, ctaLabel]);

  return (
    <Dialog open onClose={onClose} variant="sheet" size="lg" title={initial ? `ویرایش «${initial.name}»` : 'کمپین تازه'}
      description={initial?.status === 'approved' ? 'تغییر کمپین تأییدشده، آن را دوباره به صف بررسی می‌فرستد.' : 'پیش از نمایش، کمپین توسط کافه‌گردی بررسی می‌شود.'}
      footer={(
        <div className="flex flex-wrap items-center gap-2">
          {!campaign || campaign.status === 'draft' || campaign.status === 'rejected'
            ? <Button icon={<Send />} loading={pending} onClick={onSubmit} disabled={needsImage && !campaign?.image_url}>ذخیره و ارسال برای بررسی</Button> : null}
          <Button variant="secondary" loading={pending} onClick={onSave}>ذخیره</Button>
          {needsImage && !campaign?.image_url ? <span className="text-xs text-text-muted">برای ارسال بنر، تصویر لازم است.</span> : null}
        </div>
      )}>
      <div className="flex flex-col gap-5">
        {/* Live preview of exactly what visitors will see. */}
        <section aria-label="پیش‌نمایش" className="flex flex-col gap-2">
          <p className="text-xs font-semibold text-text-muted">پیش‌نمایش</p>
          {input.placement === 'home_banner' ? (
            <div className="relative aspect-[16/7] overflow-hidden rounded-2xl shadow-[var(--shadow-md)]">
              {campaign?.image_url
                // eslint-disable-next-line @next/next/no-img-element -- ad creative from object storage
                ? <img src={campaign.image_url} alt="" className="absolute inset-0 size-full object-cover" />
                : <div className="absolute inset-0"><CoverArt store={store.slug} name={store.name} size="lg" /></div>}
              <div className="absolute inset-0 bg-gradient-to-l from-scrim via-scrim/35 to-transparent" aria-hidden="true" />
              <div className="relative flex h-full max-w-[70%] flex-col justify-center gap-1.5 p-4 text-on-media sm:p-6">
                <span className="glass-light inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"><Megaphone className="size-3" aria-hidden="true" />تبلیغ</span>
                <span className="flex items-center gap-2 text-xs"><Logo url={store.logo_url} name={store.name} className="size-6 rounded-lg text-xs" />{store.name}</span>
                <p className="text-lg font-black leading-tight sm:text-2xl">{input.headline || 'تیتر تبلیغ شما'}</p>
                {input.body ? <p className="line-clamp-1 text-xs text-on-media-muted sm:text-sm">{input.body}</p> : null}
                <span className="mt-1 inline-flex w-fit items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-on-brand">{ctaLabel}<ArrowLeft className="size-3" aria-hidden="true" /></span>
              </div>
            </div>
          ) : cardPreview ? (
            <div className="pointer-events-none mx-auto w-full max-w-xs" aria-hidden="true"><StoreCard s={cardPreview} /></div>
          ) : <p className="rounded-xl bg-surface-muted p-4 text-sm text-text-muted">پیش‌نمایش کارت پس از تکمیل بازارگاه نمایش داده می‌شود.</p>}
        </section>

        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1.5 text-sm font-medium">جایگاه</legend>
          <div className="grid gap-2 sm:grid-cols-2">
            {data.placements.map((p) => (
              <label key={p.key} className={cx('flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors', input.placement === p.key ? 'border-brand bg-brand-soft' : 'border-border hover:bg-surface-muted')}>
                <input type="radio" name="placement" value={p.key} checked={input.placement === p.key} onChange={() => set('placement', p.key)} className="mt-1 accent-[var(--color-brand)]" />
                <span>
                  <span className="block text-sm font-semibold">{p.name}</span>
                  <span className="block text-xs text-text-muted">{p.description}</span>
                  <span className="mt-1 block text-xs font-bold text-brand">روزی {formatMoney(p.daily_price)}</span>
                </span>
              </label>
            ))}
          </div>
          {errors.placement ? <p role="alert" className="text-xs text-danger">{errors.placement}</p> : null}
        </fieldset>

        <TextField label="نام کمپین (فقط برای خودتان)" value={input.name} maxLength={80} required onChange={(e) => set('name', e.target.value)} error={errors.name} placeholder="مثلاً «صبحانه‌ی پاییز»" />

        <div className="grid gap-4 sm:grid-cols-2">
          <JalaliDateField label="روز شروع" name="start_date" defaultValue={input.start_date} years={2} future onChange={(v) => set('start_date', v)} />
          <fieldset className="flex flex-col gap-1.5">
            <legend className="mb-1.5 text-sm font-medium">چند روز؟</legend>
            <div className="flex flex-wrap gap-1.5">
              {DAY_CHOICES.map((d) => (
                <button key={d} type="button" onClick={() => set('days', d)} aria-pressed={input.days === d}
                  className={cx('h-10 min-w-12 rounded-lg border px-3 text-sm', input.days === d ? 'border-brand bg-brand text-on-brand' : 'border-border-strong hover:bg-surface-muted')}>{toPersianDigits(d)}</button>
              ))}
              <select aria-label="تعداد روز دلخواه" value={DAY_CHOICES.includes(input.days) ? '' : input.days} onChange={(e) => e.target.value && set('days', Number(e.target.value))} className="h-10 rounded-lg border border-border-strong bg-surface px-2 text-sm">
                <option value="">دلخواه</option>
                {Array.from({ length: 30 }, (_, i) => i + 1).filter((d) => !DAY_CHOICES.includes(d)).map((d) => <option key={d} value={d}>{toPersianDigits(d)} روز</option>)}
              </select>
            </div>
            {errors.start_date || errors.days ? <p role="alert" className="text-xs text-danger">{errors.start_date || errors.days}</p> : null}
          </fieldset>
        </div>

        {data.cities.length > 1 ? (
          <fieldset className="flex flex-col gap-1.5">
            <legend className="mb-1.5 text-sm font-medium">شهرها</legend>
            <div className="flex flex-wrap gap-1.5">
              {data.cities.map((city) => {
                const on = input.cities.includes(city);

                return (
                  <button key={city} type="button" aria-pressed={on} onClick={() => set('cities', on ? input.cities.filter((x) => x !== city) : [...input.cities, city])}
                    className={cx('h-9 rounded-full border px-3 text-sm', on ? 'border-brand bg-brand-soft font-semibold text-brand-strong' : 'border-border hover:bg-surface-muted')}>{city}</button>
                );
              })}
            </div>
            <p className="text-xs text-text-muted">{input.cities.length ? 'فقط به بازدیدکنندگان این شهرها نشان داده می‌شود.' : 'هیچ‌کدام انتخاب نشود یعنی همه‌ی شهرها.'}</p>
          </fieldset>
        ) : null}

        <TextField label="تیتر" value={input.headline} maxLength={40} required onChange={(e) => set('headline', e.target.value)} error={errors.headline}
          hint={`${toPersianDigits(input.headline.length)} از ۴۰ نویسه`} placeholder="مثلاً «صبحانه‌ی آخر هفته با قهوه‌ی دمی»" />
        <TextField label="متن کوتاه (اختیاری)" value={input.body} maxLength={90} onChange={(e) => set('body', e.target.value)} error={errors.body} hint={`${toPersianDigits(input.body.length)} از ۹۰ نویسه`} />
        <SelectField label="دکمه" value={input.cta} onChange={(e) => set('cta', e.target.value)} error={errors.cta}>
          {data.ctas.map((c) => <option key={c.key} value={c.key}>{c.label}</option>)}
        </SelectField>

        <div className="flex flex-col gap-2">
          <p className="text-sm font-medium">تصویر {needsImage ? '(لازم برای بنر)' : '(اختیاری)'}</p>
          <div className="flex flex-wrap items-center gap-3">
            {campaign?.image_url ? <Thumb c={campaign} store={store} className="aspect-[16/9] w-32" /> : null}
            <input ref={file} type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" aria-label="انتخاب تصویر" onChange={(e) => { const f = e.target.files?.[0]; if (f) onImage(f); e.target.value = ''; }} />
            <Button variant="secondary" icon={<ImagePlus />} loading={pending} onClick={() => file.current?.click()}>{campaign?.image_url ? 'تعویض تصویر' : 'انتخاب تصویر'}</Button>
            {campaign?.image_url ? <Button variant="ghost" icon={<Trash2 />} onClick={removeImage}>حذف</Button> : null}
          </div>
          <p className="text-xs text-text-muted">افقی و دست‌کم ۹۶۰×۳۶۰ پیکسل؛ بهترین نسبت ۱۶ به ۶. متن مهم را روی تصویر ننویسید؛ تیتر روی آن می‌آید.</p>
        </div>

        {quote ? (
          <div className="flex flex-col gap-2 rounded-2xl border border-border bg-surface-muted p-4 text-sm">
            <p className="flex justify-between"><span className="text-text-muted">روزی {formatMoney(quote.daily_price)} × {toPersianDigits(quote.days)} روز</span><span>{formatMoney(quote.subtotal)}</span></p>
            <p className="flex justify-between"><span className="text-text-muted">مالیات بر ارزش افزوده (٪{toPersianDigits(quote.vat_rate)})</span><span>{formatMoney(quote.vat)}</span></p>
            <p className="flex justify-between border-t border-border pt-2 text-base font-bold"><span>مبلغ قابل پرداخت پس از تأیید</span><span>{formatMoney(quote.total)}</span></p>
            <p className={cx('inline-flex items-center gap-1.5 text-xs font-semibold', quote.available ? 'text-success' : 'text-danger')}>
              <BadgePercent className="size-3.5" aria-hidden="true" />{quote.available ? `جای خالی در این روزها: ${toPersianDigits(quote.remaining)}` : 'این جایگاه در این روزها پر است؛ روز دیگری انتخاب کنید.'}
            </p>
          </div>
        ) : null}
        {message ? <p role="alert" className="text-sm text-danger">{message}</p> : null}
      </div>
    </Dialog>
  );
}
