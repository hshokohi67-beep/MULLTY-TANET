'use client';

import { useMemo, useState, useTransition } from 'react';
import { LayoutGrid, Search, Trash2 } from 'lucide-react';
import { Alert, Badge, Button, Card, CardHeader, Checkbox, cx, SelectField, TextField } from '@cafe/ui';
import { formatJalaliLong, formatNumber, normalizeForSearch, todayIn } from '@cafe/locale';
import { deleteCollection, saveCollection } from '@/app/actions/collections';
import { JalaliDateField } from '@/components/JalaliDateField';
import { THEME_LABELS, type SeasonTheme } from '@/lib/occasion-types';
import { isoToLocal, localToIso } from '@/lib/operations-types';

export interface MenuCollection {
  id: string;
  title: string;
  subtitle: string | null;
  theme: SeasonTheme | null;
  starts_at: string | null;
  ends_at: string | null;
  occasion_key: string | null;
  is_active: boolean;
  products: { id: string; name: string }[];
}

interface Prefill { title: string; theme: string | null; occasion_key: string | null; from: string | null; to: string | null }

function phase(c: MenuCollection): { label: string; tone: 'success' | 'info' | 'neutral' } {
  const now = Date.now();
  if (!c.is_active) return { label: 'خاموش', tone: 'neutral' };
  if (c.ends_at && Date.parse(c.ends_at) <= now) return { label: 'تمام شده', tone: 'neutral' };
  if (c.starts_at && Date.parse(c.starts_at) > now) return { label: 'زمان‌بندی‌شده', tone: 'info' };

  return { label: 'روی منو', tone: 'success' };
}

/** One collection: a summary row that opens into its form (or the «new» form, possibly prefilled). */
export function CollectionEditor({ collection, products, timezone, prefill = null, readOnly = false }: {
  collection?: MenuCollection;
  products: { id: string; name: string }[];
  timezone: string;
  prefill?: Prefill | null;
  readOnly?: boolean;
}) {
  const [open, setOpen] = useState(collection === undefined && prefill !== null);
  const [title, setTitle] = useState(collection?.title ?? prefill?.title ?? '');
  const [subtitle, setSubtitle] = useState(collection?.subtitle ?? '');
  const [theme, setTheme] = useState<string>(collection?.theme ?? (prefill?.theme && prefill.theme in THEME_LABELS ? prefill.theme : ''));
  const [from, setFrom] = useState(collection?.starts_at ? isoToLocal(collection.starts_at, timezone).date : prefill?.from ?? todayIn(timezone));
  const [hasEnd, setHasEnd] = useState(Boolean(collection?.ends_at ?? prefill?.to));
  const [to, setTo] = useState(collection?.ends_at ? isoToLocal(collection.ends_at, timezone).date : prefill?.to ?? todayIn(timezone));
  const [active, setActive] = useState(collection?.is_active ?? true);
  const [picked, setPicked] = useState<string[]>(collection?.products.map((p) => p.id) ?? []);
  const [query, setQuery] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [confirming, setConfirming] = useState(false);
  const [pending, start] = useTransition();

  const shown = useMemo(() => {
    const q = normalizeForSearch(query.trim());

    return q ? products.filter((p) => normalizeForSearch(p.name).includes(q)) : products;
  }, [products, query]);

  if (!open) {
    if (!collection) return <div><Button icon={<LayoutGrid className="size-4" />} onClick={() => setOpen(true)}>منوی مناسبتی تازه</Button></div>;
    const p = phase(collection);

    return (
      <Card>
        <CardHeader
          title={collection.title}
          description={[
            collection.subtitle,
            `${formatNumber(collection.products.length)} محصول`,
            collection.starts_at ? `از ${formatJalaliLong(collection.starts_at, timezone)}` : null,
            collection.ends_at ? `تا ${formatJalaliLong(collection.ends_at, timezone)}` : null,
            collection.theme ? `تزئین ${THEME_LABELS[collection.theme]}` : null,
          ].filter(Boolean).join(' • ')}
          actions={<>
            <Badge tone={p.tone}>{p.label}</Badge>
            {readOnly ? null : <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>ویرایش</Button>}
          </>}
        />
      </Card>
    );
  }

  const save = () => start(async () => {
    setError(null);
    if (picked.length === 0) { setError('دست‌کم یک محصول انتخاب کنید.'); return; }
    const r = await saveCollection(collection?.id ?? null, {
      title: title.trim(),
      subtitle: subtitle.trim() || null,
      theme: theme || null,
      starts_at: localToIso(from, '00:00', timezone),
      ends_at: hasEnd ? localToIso(to, '23:59', timezone) : null,
      occasion_key: collection?.occasion_key ?? prefill?.occasion_key ?? null,
      is_active: active,
      product_ids: picked,
    });
    if (!r.ok) { setError(Object.values(r.errors ?? {})[0] ?? r.message ?? 'ذخیره نشد.'); return; }
    setOpen(false);
  });

  const toggle = (id: string) => setPicked((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : prev.length < 20 ? [...prev, id] : prev));

  return (
    <Card>
      <CardHeader icon={<LayoutGrid />} title={collection ? `ویرایش «${collection.title}»` : 'منوی مناسبتی تازه'} />
      <div className="flex flex-col gap-4 p-5">
        {error ? <Alert tone="danger">{error}</Alert> : null}
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label="عنوان" value={title} onChange={(e) => setTitle(e.target.value)} maxLength={80} required placeholder="مثلاً طعم‌های پاییزی" />
          <TextField label="زیرعنوان (اختیاری)" value={subtitle} onChange={(e) => setSubtitle(e.target.value)} maxLength={160} placeholder="دارچین، کدو حلوایی و ادویه‌های گرم" />
          <SelectField label="تزئین فصلی صفحه‌ها" value={theme} onChange={(e) => setTheme(e.target.value)} hint="ملایم و فقط در حاشیه؛ روی متن و قیمت نمی‌آید.">
            <option value="">بدون تزئین</option>
            {(Object.keys(THEME_LABELS) as SeasonTheme[]).map((t) => <option key={t} value={t}>{THEME_LABELS[t]}</option>)}
          </SelectField>
          <div className="flex flex-col justify-end"><Checkbox label="فعال" checked={active} onChange={(e) => setActive(e.target.checked)} /></div>
        </div>

        <div className="flex flex-wrap items-end gap-4">
          <JalaliDateField label="از روز" name="from" defaultValue={from} years={2} future onChange={setFrom} />
          <Checkbox label="تا روز مشخص" checked={hasEnd} onChange={(e) => setHasEnd(e.target.checked)} />
          {hasEnd ? <JalaliDateField label="تا روز" name="to" defaultValue={to} years={2} future onChange={setTo} /> : null}
        </div>

        <fieldset className="flex flex-col gap-2 rounded-2xl border border-border p-3.5">
          <legend className="px-1 text-sm font-medium">محصولات {picked.length ? <span className="font-normal text-text-muted">({formatNumber(picked.length)} از ۲۰)</span> : null}</legend>
          <label className="relative block">
            <span className="sr-only">جست‌وجوی محصول</span>
            <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-text-subtle" aria-hidden="true" />
            <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="جست‌وجوی محصول…"
              className="h-10 w-full rounded-xl border border-border-strong bg-surface ps-9 pe-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
          </label>
          <ul className="grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2">
            {shown.map((p) => (
              <li key={p.id}>
                <label className={cx('flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-surface-muted', picked.includes(p.id) && 'bg-brand-soft')}>
                  <input type="checkbox" checked={picked.includes(p.id)} onChange={() => toggle(p.id)} className="accent-[var(--color-brand)]" />
                  <span className="truncate">{p.name}</span>
                </label>
              </li>
            ))}
            {shown.length === 0 ? <li className="px-2 py-3 text-sm text-text-muted">محصولی با این نام نیست.</li> : null}
          </ul>
        </fieldset>

        <div className="flex flex-wrap items-center gap-2">
          <Button onClick={save} loading={pending} disabled={!title.trim()}>ذخیره</Button>
          <Button variant="ghost" onClick={() => setOpen(false)}>بستن</Button>
          {collection ? (confirming ? (
            <span className="flex items-center gap-2 text-sm">
              <span className="text-text-muted">حذف شود؟</span>
              <Button size="sm" variant="danger" onClick={() => start(() => deleteCollection(collection.id))}>حذف</Button>
              <Button size="sm" variant="ghost" onClick={() => setConfirming(false)}>نه</Button>
            </span>
          ) : <Button variant="ghost" icon={<Trash2 className="size-4" />} onClick={() => setConfirming(true)}>حذف</Button>) : null}
        </div>
      </div>
    </Card>
  );
}
