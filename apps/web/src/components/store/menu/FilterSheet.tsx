'use client';

import { useState } from 'react';
import { Flame, Leaf, Snowflake } from 'lucide-react';
import { Button, cx, Dialog } from '@cafe/ui';
import { formatMoney, formatNumber, toLatinDigits } from '@cafe/locale';
import { CALORIE_LABELS, NO_FILTERS, SORT_LABELS, type CalorieRange, type MenuFilters, type MenuSort } from '@/lib/menu-logic';
import type { Mood } from '@/lib/storefront-types';

const chip = (on: boolean) => cx('inline-flex h-10 items-center gap-1.5 rounded-full border px-4 text-sm transition-colors',
  on ? 'border-brand bg-brand-soft font-semibold text-text' : 'border-border text-text-muted hover:border-border-strong hover:text-text');

/** Price steps offered as "up to …" (rial), cut to the menu's own range. */
function priceSteps(maxPrice: number): number[] {
  return [500_000, 1_000_000, 1_500_000, 2_000_000, 3_000_000, 5_000_000].filter((p) => p < maxPrice).slice(0, 5);
}

/**
 * All menu filters in one sheet: calories (when the café shows them), sorting, hot/cold, dietary
 * tags, price and "available only". Edits a draft; «نمایش نتایج» applies it.
 */
export function FilterSheet({ open, onClose, value, onApply, showCalories, tags, maxPrice, count }: {
  open: boolean;
  onClose: () => void;
  value: MenuFilters;
  onApply: (f: MenuFilters) => void;
  showCalories: boolean;
  tags: { key: string; label: string }[];
  maxPrice: number;
  /** Live result count for the draft. */
  count: (f: MenuFilters) => number;
}) {
  const [draft, setDraft] = useState(value);
  const [custom, setCustom] = useState(value.maxCalories ? String(value.maxCalories) : '');
  const set = (patch: Partial<MenuFilters>) => setDraft((d) => ({ ...d, ...patch }));
  const toggle = <T,>(list: T[], item: T) => (list.includes(item) ? list.filter((x) => x !== item) : [...list, item]);
  const sorts = (Object.keys(SORT_LABELS) as MenuSort[]).filter((s) => showCalories || s !== 'light');
  const n = count(draft);

  return (
    <Dialog open={open} onClose={onClose} title="فیلتر و مرتب‌سازی" variant="sheet"
      footer={(
        <div className="flex w-full gap-2">
          <Button variant="ghost" onClick={() => { setDraft(NO_FILTERS); setCustom(''); }}>پاک کردن همه</Button>
          <Button className="flex-1" onClick={() => { onApply(draft); onClose(); }}>{n ? `نمایش ${formatNumber(n)} مورد` : 'موردی پیدا نشد'}</Button>
        </div>
      )}>
      <div className="flex flex-col gap-6">
        <fieldset>
          <legend className="mb-2 text-sm font-semibold">مرتب‌سازی</legend>
          <div className="flex flex-wrap gap-2">
            {sorts.map((s) => <button key={s} type="button" aria-pressed={draft.sort === s} onClick={() => set({ sort: s })} className={chip(draft.sort === s)}>{SORT_LABELS[s]}</button>)}
          </div>
        </fieldset>

        {showCalories ? (
          <fieldset>
            <legend className="mb-2 flex items-center gap-1.5 text-sm font-semibold"><Flame className="size-4 text-text-subtle" aria-hidden="true" />کالری</legend>
            <div className="flex flex-wrap gap-2">
              <button type="button" aria-pressed={draft.calories === 'any'} onClick={() => set({ calories: 'any', maxCalories: null })} className={chip(draft.calories === 'any')}>همه</button>
              {(Object.keys(CALORIE_LABELS) as (keyof typeof CALORIE_LABELS)[]).map((r) => (
                <button key={r} type="button" aria-pressed={draft.calories === r} onClick={() => set({ calories: r as CalorieRange, maxCalories: null })} className={chip(draft.calories === r)}>{CALORIE_LABELS[r]}</button>
              ))}
            </div>
            <label className="mt-3 flex items-center gap-2 text-sm text-text-muted">
              یا حداکثر
              <input inputMode="numeric" value={custom} placeholder="۳۵۰" aria-label="حداکثر کالری"
                onChange={(e) => {
                  const raw = toLatinDigits(e.target.value).replace(/\D/g, '').slice(0, 4);
                  setCustom(raw);
                  set(raw ? { calories: 'max', maxCalories: Number(raw) } : { calories: 'any', maxCalories: null });
                }}
                className={cx('tabular h-10 w-24 rounded-xl border bg-surface px-3 text-center text-text outline-none focus:border-brand', draft.calories === 'max' ? 'border-brand' : 'border-border')} />
              کالری
            </label>
          </fieldset>
        ) : null}

        <fieldset>
          <legend className="mb-2 text-sm font-semibold">گرم یا سرد</legend>
          <div className="flex flex-wrap gap-2">
            {([['hot', 'گرم', Flame], ['cold', 'سرد', Snowflake]] as [Mood, string, typeof Flame][]).map(([m, label, Icon]) => (
              <button key={m} type="button" data-mood={m} aria-pressed={draft.moods.includes(m)} onClick={() => set({ moods: toggle(draft.moods, m) })} className={chip(draft.moods.includes(m))}>
                <Icon className="size-4 text-[var(--mood-ink)]" aria-hidden="true" />{label}
              </button>
            ))}
          </div>
        </fieldset>

        {tags.length ? (
          <fieldset>
            <legend className="mb-2 text-sm font-semibold">رژیمی و ویژه</legend>
            <div className="flex flex-wrap gap-2">
              {tags.map((t) => (
                <button key={t.key} type="button" aria-pressed={draft.tags.includes(t.key)} onClick={() => set({ tags: toggle(draft.tags, t.key) })} className={chip(draft.tags.includes(t.key))}>
                  <Leaf className="size-4 text-success" aria-hidden="true" />{t.label}
                </button>
              ))}
            </div>
          </fieldset>
        ) : null}

        {priceSteps(maxPrice).length ? (
          <fieldset>
            <legend className="mb-2 text-sm font-semibold">قیمت</legend>
            <div className="flex flex-wrap gap-2">
              <button type="button" aria-pressed={draft.maxPrice === null} onClick={() => set({ maxPrice: null })} className={chip(draft.maxPrice === null)}>همه</button>
              {priceSteps(maxPrice).map((p) => (
                <button key={p} type="button" aria-pressed={draft.maxPrice === p} onClick={() => set({ maxPrice: p })} className={chip(draft.maxPrice === p)}>تا {formatMoney(p)}</button>
              ))}
            </div>
          </fieldset>
        ) : null}

        <label className="flex cursor-pointer items-center justify-between gap-3 rounded-2xl bg-surface-muted px-4 py-3 text-sm">
          فقط محصولات موجود
          <input type="checkbox" checked={draft.availableOnly} onChange={(e) => set({ availableOnly: e.target.checked })} className="size-5 accent-[var(--color-brand)]" />
        </label>
      </div>
    </Dialog>
  );
}
