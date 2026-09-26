'use client';

import { useState } from 'react';
import { ArrowDown, ArrowUp, ChevronDown, Eye, EyeOff, Plus, Search, Trash2 } from 'lucide-react';
import { Button, Card, CardHeader, IconButton, TextAreaField, TextField, cx } from '@cafe/ui';
import { formatMoney, formatNumber, normalizeForSearch } from '@cafe/locale';
import { SECTION_LABELS, type LandingContent, type SectionKey } from '@/lib/landing-types';
import type { MenuProduct } from '@/lib/storefront-types';
import type { Draft } from './LandingEditor';

const MAX_FEATURED = 6;
const MAX_HIGHLIGHTS = 4;
const MAX_PHRASES = 4;

/** Words of the hero, then every section: show/hide, move, pick a layout and fill it in. */
export function ContentPanel({ draft, onChange, products, galleryCount, onGoToMedia }: {
  draft: Draft;
  onChange: (d: Draft) => void;
  products: MenuProduct[];
  galleryCount: number;
  onGoToMedia: () => void;
}) {
  const [open, setOpen] = useState<SectionKey | null>(null);
  const content = draft.content;
  const setContent = <S extends keyof LandingContent>(section: S, patch: Partial<LandingContent[S]>) =>
    onChange({ ...draft, content: { ...content, [section]: { ...content[section], ...patch } } });

  const move = (i: number, by: number) => {
    const sections = [...draft.sections];
    const j = i + by;
    if (j < 0 || j >= sections.length) return;
    [sections[i], sections[j]] = [sections[j], sections[i]];
    onChange({ ...draft, sections });
  };
  const toggle = (i: number) => onChange({ ...draft, sections: draft.sections.map((s, k) => (k === i ? { ...s, visible: !s.visible } : s)) });

  return (
    <div className="flex flex-col gap-4">
      <Card className="flex flex-col gap-4 p-5">
        <CardHeader title="سردر" description="اولین چیزی که مشتری می‌بیند. کوتاه و خودمانی بنویسید." />
        <TextField label="خط کوچک بالای تیتر" maxLength={40} placeholder="مثلاً: از ۱۳۹۸، کنار دانشگاه" value={content.hero.eyebrow ?? ''} onChange={(e) => setContent('hero', { eyebrow: e.target.value || null })} />
        <TextField label="تیتر اصلی" required maxLength={80} value={content.hero.title} onChange={(e) => setContent('hero', { title: e.target.value })} />
        <TextAreaField label="متن زیر تیتر" rows={2} maxLength={220} placeholder="مثلاً: قهوه‌ی تازه‌برشت، کیک خانگی و جایی برای آرام نشستن." value={content.hero.subtitle ?? ''} onChange={(e) => setContent('hero', { subtitle: e.target.value || null })} />
        <TextField label="متن دکمه‌ی منو" required maxLength={24} value={content.hero.cta_label} onChange={(e) => setContent('hero', { cta_label: e.target.value })} />
      </Card>

      <Card className="flex flex-col gap-3 p-5">
        <CardHeader title="بخش‌ها" description="ترتیب را با فلش‌ها عوض کنید؛ چشم، بخش را نشان می‌دهد یا پنهان می‌کند. بخش خالی خودبه‌خود نمایش داده نمی‌شود." />
        <ol className="flex flex-col gap-2">
          {draft.sections.map((s, i) => {
            const label = SECTION_LABELS[s.key];
            const expanded = open === s.key;

            return (
              <li key={s.key} className={cx('rounded-2xl border transition-colors', expanded ? 'border-brand/50' : 'border-border', !s.visible && 'opacity-70')}>
                <div className="flex items-center gap-1 p-2 ps-3">
                  <button type="button" aria-expanded={expanded} onClick={() => setOpen(expanded ? null : s.key)} className="flex min-w-0 flex-1 items-center gap-2 text-start">
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-bold tabular">{formatNumber(i + 1)}</span>
                    <span className="min-w-0">
                      <span className="block font-semibold">{label.title}</span>
                      <span className="block truncate text-xs text-text-muted">{label.hint}</span>
                    </span>
                    <ChevronDown className={cx('ms-auto size-4 shrink-0 text-text-muted transition-transform', expanded && 'rotate-180')} aria-hidden="true" />
                  </button>
                  <IconButton label={s.visible ? 'پنهان کردن' : 'نمایش'} onClick={() => toggle(i)}>{s.visible ? <Eye className="size-4" /> : <EyeOff className="size-4" />}</IconButton>
                  <IconButton label="بالاتر" onClick={() => move(i, -1)} disabled={i === 0}><ArrowUp className="size-4" /></IconButton>
                  <IconButton label="پایین‌تر" onClick={() => move(i, 1)} disabled={i === draft.sections.length - 1}><ArrowDown className="size-4" /></IconButton>
                </div>

                {expanded ? (
                  <div className="flex flex-col gap-4 border-t border-border p-4">
                    <Variants section={s.key} value={(content[s.key] as { variant: string }).variant}
                      onChange={(variant) => setContent(s.key, { variant } as Partial<LandingContent[typeof s.key]>)} />
                    <SectionFields section={s.key} content={content} setContent={setContent} products={products} galleryCount={galleryCount} onGoToMedia={onGoToMedia} />
                  </div>
                ) : null}
              </li>
            );
          })}
        </ol>
      </Card>
    </div>
  );
}

function Variants({ section, value, onChange }: { section: SectionKey; value: string; onChange: (v: string) => void }) {
  return (
    <fieldset>
      <legend className="mb-2 text-sm font-semibold">چیدمان</legend>
      <div className="flex flex-wrap gap-2">
        {Object.entries(SECTION_LABELS[section].variants).map(([key, label]) => (
          <button key={key} type="button" aria-pressed={value === key} onClick={() => onChange(key)}
            className={cx('h-9 rounded-full border px-4 text-sm', value === key ? 'border-brand bg-brand-soft font-semibold' : 'border-border text-text-muted hover:text-text')}>
            {label}
          </button>
        ))}
      </div>
    </fieldset>
  );
}

function SectionFields({ section, content, setContent, products, galleryCount, onGoToMedia }: {
  section: SectionKey;
  content: LandingContent;
  setContent: <S extends keyof LandingContent>(section: S, patch: Partial<LandingContent[S]>) => void;
  products: MenuProduct[];
  galleryCount: number;
  onGoToMedia: () => void;
}) {
  const [query, setQuery] = useState('');

  switch (section) {
    case 'story':
      return (
        <>
          <TextField label="عنوان" maxLength={80} value={content.story.title ?? ''} onChange={(e) => setContent('story', { title: e.target.value || null })} />
          <TextAreaField label="متن" rows={6} maxLength={1500} hint="برای پاراگراف تازه یک خط خالی بگذارید. عکس این بخش در «عکس و ویدیو» است."
            placeholder="از کجا شروع کردید، چه چیزی کافه‌تان را خاص می‌کند، دانه‌ها از کجا می‌آیند…"
            value={content.story.text ?? ''} onChange={(e) => setContent('story', { text: e.target.value || null })} />
        </>
      );

    case 'highlights': {
      const items = content.highlights.items;
      const setItem = (i: number, patch: Partial<(typeof items)[number]>) => setContent('highlights', { items: items.map((it, k) => (k === i ? { ...it, ...patch } : it)) });

      return (
        <>
          <TextField label="عنوان بخش (اختیاری)" maxLength={80} value={content.highlights.title ?? ''} onChange={(e) => setContent('highlights', { title: e.target.value || null })} />
          {items.map((it, i) => (
            <div key={i} className="grid grid-cols-[5rem_4rem_1fr_auto] items-end gap-2">
              <TextField label="عدد" maxLength={12} placeholder="۱۰۰" value={it.value} onChange={(e) => setItem(i, { value: e.target.value })} />
              <TextField label="واحد" maxLength={12} placeholder="٪" value={it.unit ?? ''} onChange={(e) => setItem(i, { unit: e.target.value || null })} />
              <TextField label="توضیح" maxLength={80} placeholder="دانه‌ی تازه‌برشت" value={it.label} onChange={(e) => setItem(i, { label: e.target.value })} />
              <IconButton label="حذف" onClick={() => setContent('highlights', { items: items.filter((_, k) => k !== i) })}><Trash2 className="size-4" /></IconButton>
            </div>
          ))}
          {items.length < MAX_HIGHLIGHTS ? (
            <Button variant="secondary" size="sm" icon={<Plus className="size-4" />} onClick={() => setContent('highlights', { items: [...items, { value: '', unit: null, label: '' }] })}>افزودن کارت</Button>
          ) : null}
          <p className="text-xs text-text-subtle">مثال: «۰ گرم • شکر افزوده»، «۱۲ نوع • دمنوش»، «۷ روز هفته • باز هستیم».</p>
        </>
      );
    }

    case 'featured': {
      const ids = content.featured.product_ids;
      const q = normalizeForSearch(query);
      const list = products.filter((p) => !q || normalizeForSearch(p.name).includes(q));
      const toggle = (id: string) => setContent('featured', { product_ids: ids.includes(id) ? ids.filter((x) => x !== id) : ids.length < MAX_FEATURED ? [...ids, id] : ids });

      return (
        <>
          <TextField label="عنوان بخش" maxLength={80} value={content.featured.title ?? ''} onChange={(e) => setContent('featured', { title: e.target.value || null })} />
          <p className="text-sm text-text-muted">
            {ids.length ? `${formatNumber(ids.length)} از ${formatNumber(MAX_FEATURED)} محصول انتخاب شده.` : 'چیزی انتخاب نکنید تا «پیشنهاد ما»ی منو خودکار نمایش داده شود.'}
          </p>
          {products.length > 8 ? (
            <label className="relative">
              <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-text-subtle" aria-hidden="true" />
              <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="جست‌وجوی محصول…" aria-label="جست‌وجوی محصول"
                className="h-10 w-full rounded-xl border border-border bg-surface ps-9 text-sm outline-none focus:border-brand" />
            </label>
          ) : null}
          <ul className="flex max-h-72 flex-col gap-1 overflow-y-auto">
            {list.map((p) => {
              const on = ids.includes(p.id);

              return (
                <li key={p.id}>
                  <label className={cx('flex cursor-pointer items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-surface-muted', !on && ids.length >= MAX_FEATURED && 'opacity-50')}>
                    <input type="checkbox" checked={on} onChange={() => toggle(p.id)} disabled={!on && ids.length >= MAX_FEATURED} className="size-4 accent-[var(--color-brand)]" />
                    <span className="flex-1 truncate text-sm">{p.name}</span>
                    {on ? <span className="text-xs font-semibold text-brand">{formatNumber(ids.indexOf(p.id) + 1)}</span> : null}
                    <span className="text-xs text-text-subtle tabular">{formatMoney(p.price_from)}</span>
                  </label>
                </li>
              );
            })}
          </ul>
        </>
      );
    }

    case 'marquee': {
      const phrases = [...content.marquee.phrases, ...Array(Math.max(0, MAX_PHRASES - content.marquee.phrases.length)).fill('')].slice(0, MAX_PHRASES) as string[];

      return (
        <div className="grid gap-2 sm:grid-cols-2">
          {phrases.map((p, i) => (
            <TextField key={i} label={`عبارت ${formatNumber(i + 1)}`} maxLength={40} placeholder={['قهوه‌ی تازه', 'کیک خانگی', 'صبحانه تا ظهر', 'بیرون‌بر'][i]} value={p}
              onChange={(e) => setContent('marquee', { phrases: phrases.map((x, k) => (k === i ? e.target.value : x)).filter((x, k, all) => x !== '' || all.slice(k + 1).some(Boolean)) })} />
          ))}
        </div>
      );
    }

    case 'gallery':
      return (
        <>
          <TextField label="عنوان بخش" maxLength={80} value={content.gallery.title ?? ''} onChange={(e) => setContent('gallery', { title: e.target.value || null })} />
          <p className="text-sm text-text-muted">
            {galleryCount ? `${formatNumber(galleryCount)} عکس در گالری.` : 'هنوز عکسی در گالری نیست.'}{' '}
            <button type="button" onClick={onGoToMedia} className="font-semibold text-brand hover:underline">افزودن یا مرتب کردن عکس‌ها</button>
          </p>
        </>
      );

    case 'visit':
      return (
        <>
          <TextField label="عنوان بخش" maxLength={80} value={content.visit.title ?? ''} onChange={(e) => setContent('visit', { title: e.target.value || null })} />
          <p className="text-sm text-text-muted">آدرس، ساعت کاری و تلفن هر شعبه خودکار از بخش «شعبه‌ها» خوانده می‌شود.</p>
        </>
      );
  }
}
