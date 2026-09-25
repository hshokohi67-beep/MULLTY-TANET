'use client';

import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import { Check, Save } from 'lucide-react';
import { Alert, Button, Card, cx, TextAreaField, TextField } from '@cafe/ui';
import { toPersianDigits } from '@cafe/locale';
import { saveListing } from '@/app/actions/marketplace';
import { AMENITY_ICONS, CATEGORY_ICONS } from '@/components/explore/ExploreParts';

export interface ListingData {
  is_listed: boolean; headline: string | null; about: string | null; categories: string[]; amenities: string[]; price_level: number | null; hidden_reason?: string | null;
}

type Catalog = { categories: { key: string; label: string }[]; amenities: { key: string; label: string }[]; price_levels: { key: number; label: string }[]; max_categories: number };

export function ListingEditor({ listing, catalog }: { listing: ListingData; catalog: Catalog }) {
  const router = useRouter();
  const [form, setForm] = useState<ListingData>(listing);
  const [pending, start] = useTransition();
  const [result, setResult] = useState<{ ok: boolean; message: string; errors?: Record<string, string> } | null>(null);
  const set = <K extends keyof ListingData>(key: K, value: ListingData[K]) => setForm((f) => ({ ...f, [key]: value }));
  const toggle = (key: 'categories' | 'amenities', value: string) => setForm((f) => {
    const list = f[key];
    if (list.includes(value)) return { ...f, [key]: list.filter((v) => v !== value) };
    if (key === 'categories' && list.length >= catalog.max_categories) return f;

    return { ...f, [key]: [...list, value] };
  });
  const save = () => start(async () => {
    const r = await saveListing({ ...form, categories: form.categories, amenities: form.amenities });
    setResult(r.ok ? { ok: true, message: 'ذخیره شد.' } : r);
    if (r.ok) router.refresh();
  });
  const chip = (on: boolean, disabled = false) => cx('inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors disabled:opacity-40',
    on ? 'border-brand bg-brand-soft font-semibold text-brand-strong' : 'border-border bg-surface text-text-muted hover:text-text', disabled && 'cursor-not-allowed');

  return (
    <Card>
      <div className="flex flex-col gap-5 p-5 sm:p-6">
        {listing.hidden_reason ? <Alert tone="danger" title="نمایش توسط پلتفرم متوقف شده">{listing.hidden_reason}</Alert> : null}
        <div className="flex items-center justify-between gap-4 rounded-xl bg-surface-muted p-4">
          <div>
            <p className="font-semibold">نمایش در کافه‌گردی</p>
            <p className="text-sm text-text-muted">هر وقت بخواهید خاموشش کنید؛ صفحه‌ی منوی خودتان تغییری نمی‌کند.</p>
          </div>
          <button type="button" role="switch" aria-checked={form.is_listed} aria-label="نمایش در کافه‌گردی" onClick={() => set('is_listed', !form.is_listed)}
            className={cx('relative h-7 w-12 shrink-0 rounded-full transition-colors', form.is_listed ? 'bg-brand' : 'bg-border-strong')}>
            <span className={cx('absolute top-0.5 size-6 rounded-full bg-surface shadow transition-all', form.is_listed ? 'start-[22px]' : 'start-0.5')} />
          </button>
        </div>

        <TextField label="یک جمله‌ی معرفی" value={form.headline ?? ''} onChange={(e) => set('headline', e.target.value)} maxLength={120}
          placeholder="مثلاً «قهوه‌ی تازه‌برشته و کیک خانگی در قلب شهر»" hint={`${toPersianDigits((form.headline ?? '').length)} از ۱۲۰`} error={result?.errors?.headline} />
        <TextAreaField label="درباره‌ی ما" value={form.about ?? ''} onChange={(e) => set('about', e.target.value)} maxLength={600} rows={4}
          hint={`${toPersianDigits((form.about ?? '').length)} از ۶۰۰`} error={result?.errors?.about} />

        <fieldset>
          <legend className="mb-2 text-sm font-medium">نوع کافه <span className="text-text-subtle">(حداکثر {toPersianDigits(catalog.max_categories)})</span></legend>
          <div className="flex flex-wrap gap-2">
            {catalog.categories.map((c) => {
              const on = form.categories.includes(c.key);
              const Icon = CATEGORY_ICONS[c.key];
              const full = !on && form.categories.length >= catalog.max_categories;

              return (
                <button key={c.key} type="button" onClick={() => toggle('categories', c.key)} aria-pressed={on} disabled={full} className={chip(on, full)}>
                  {on ? <Check className="size-3.5" aria-hidden="true" /> : Icon ? <Icon className="size-3.5" aria-hidden="true" /> : null}{c.label}
                </button>
              );
            })}
          </div>
          {result?.errors?.categories ? <p className="mt-1 text-sm text-danger">{result.errors.categories}</p> : null}
        </fieldset>

        <fieldset>
          <legend className="mb-2 text-sm font-medium">امکانات</legend>
          <div className="flex flex-wrap gap-2">
            {catalog.amenities.map((a) => {
              const on = form.amenities.includes(a.key);
              const Icon = AMENITY_ICONS[a.key];

              return <button key={a.key} type="button" onClick={() => toggle('amenities', a.key)} aria-pressed={on} className={chip(on)}>{Icon ? <Icon className="size-3.5" aria-hidden="true" /> : null}{a.label}</button>;
            })}
          </div>
        </fieldset>

        <fieldset>
          <legend className="mb-2 text-sm font-medium">سطح قیمت</legend>
          <div className="grid grid-cols-4 gap-1 rounded-xl bg-surface-muted p-1">
            {catalog.price_levels.map((p) => (
              <button key={p.key} type="button" onClick={() => set('price_level', form.price_level === p.key ? null : p.key)} aria-pressed={form.price_level === p.key}
                className={cx('rounded-lg py-2 text-sm', form.price_level === p.key ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text')}>{p.label}</button>
            ))}
          </div>
        </fieldset>

        {result ? <p role={result.ok ? 'status' : 'alert'} className={cx('text-sm', result.ok ? 'text-success' : 'text-danger')}>{result.message}</p> : null}
        <Button icon={<Save />} loading={pending} onClick={save} className="self-start">ذخیره</Button>
      </div>
    </Card>
  );
}
