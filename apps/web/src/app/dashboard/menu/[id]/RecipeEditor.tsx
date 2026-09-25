'use client';

import { useMemo, useState, useTransition } from 'react';
import { ChefHat, Minus, Plus, Trash2, TriangleAlert } from 'lucide-react';
import { Alert, Button, Card, CardHeader, cx } from '@cafe/ui';
import { formatMoney, formatPercent, toLatinDigits } from '@cafe/locale';
import { saveRecipe } from '@/app/actions/inventory';
import { entryUnits, type Ingredient, type Recipe } from '@/lib/inventory-types';

interface Row { key: string; ingredient_id: string; quantity: string; unit: string }

/** Above this share of the price, the ingredients alone eat too much of the sale. */
const FOOD_COST_WARNING = 0.35;

const newKey = () => Math.random().toString(36).slice(2);
const parse = (v: string) => Number(toLatinDigits(v).replace('٫', '.'));

function toRows(items: { ingredient_id: string; quantity: number; unit: string }[]): Row[] {
  return items.map((i) => ({ key: newKey(), ingredient_id: i.ingredient_id, quantity: String(Math.abs(i.quantity)), unit: i.unit }));
}

/**
 * Recipe per size and the effect of each modifier, with a live cost, margin and food-cost share
 * at current average ingredient costs. Modifier rows can add or remove (a milk swap).
 */
export function RecipeEditor({ productId, recipe, ingredients, readOnly }: { productId: string; recipe: Recipe; ingredients: Ingredient[]; readOnly: boolean }) {
  const byId = useMemo(() => Object.fromEntries(ingredients.map((i) => [i.id, i])), [ingredients]);
  const [tab, setTab] = useState(0);
  const [variants, setVariants] = useState<Record<string, Row[]>>(() => Object.fromEntries(recipe.variants.map((v) => [v.variant_id, toRows(v.items)])));
  const [modifiers, setModifiers] = useState<Record<string, { rows: Row[]; signs: Record<string, 1 | -1> }>>(() => Object.fromEntries(recipe.modifiers.map((m) => {
    const rows = toRows(m.items);
    return [m.modifier_id, { rows, signs: Object.fromEntries(rows.map((r, i) => [r.key, m.items[i].quantity < 0 ? -1 : 1])) }];
  })));
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [pending, start] = useTransition();

  const factor = (ing: Ingredient, unit: string) => (unit === 'kg' || unit === 'l' ? 1000 : unit === 'pack' ? ing.pack_size ?? 1 : 1);
  const rowCost = (r: Row) => {
    const ing = byId[r.ingredient_id];
    const q = parse(r.quantity);
    return ing && Number.isFinite(q) ? Math.round((q * factor(ing, r.unit) * ing.avg_cost) / 1000) : 0;
  };

  const variant = recipe.variants[tab];
  const rows = variant ? variants[variant.variant_id] ?? [] : [];
  const cost = rows.reduce((s, r) => s + rowCost(r), 0);
  const ratio = variant && variant.price > 0 ? cost / variant.price : null;

  const setRows = (id: string, next: Row[]) => setVariants((v) => ({ ...v, [id]: next }));

  const save = () => start(async () => {
    const line = (r: Row, sign = 1) => ({ ingredient_id: r.ingredient_id, quantity: parse(r.quantity) * sign, unit: r.unit || byId[r.ingredient_id]?.unit || 'g' });
    const result = await saveRecipe(productId, {
      variants: recipe.variants.map((v) => ({ variant_id: v.variant_id, items: (variants[v.variant_id] ?? []).filter((r) => r.ingredient_id && r.quantity).map((r) => line(r)) })),
      modifiers: recipe.modifiers.map((m) => ({ modifier_id: m.modifier_id, items: (modifiers[m.modifier_id]?.rows ?? []).filter((r) => r.ingredient_id && r.quantity).map((r) => line(r, modifiers[m.modifier_id].signs[r.key] ?? 1)) })),
    });
    setMessage({ ok: result.ok, text: result.message ?? (result.ok ? 'ذخیره شد.' : 'ذخیره نشد.') });
  });

  const rowEditor = (r: Row, onChange: (patch: Partial<Row>) => void, onRemove: () => void, sign?: { value: 1 | -1; toggle: () => void }) => {
    const ing = byId[r.ingredient_id];
    const units = ing ? entryUnits(ing.unit, { label: ing.pack_label, size: ing.pack_size }) : [];

    return (
      <div key={r.key} className="flex flex-wrap items-center gap-2">
        {sign ? (
          <button type="button" disabled={readOnly} onClick={sign.toggle} aria-label={sign.value > 0 ? 'اضافه می‌کند (برای کم‌کردن بزنید)' : 'کم می‌کند (برای اضافه‌کردن بزنید)'}
            className={cx('flex size-9 items-center justify-center rounded-lg', sign.value > 0 ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger')}>
            {sign.value > 0 ? <Plus className="size-4" /> : <Minus className="size-4" />}
          </button>
        ) : null}
        <select aria-label="ماده" value={r.ingredient_id} disabled={readOnly} onChange={(e) => onChange({ ingredient_id: e.target.value, unit: byId[e.target.value]?.unit ?? '' })}
          className="h-10 min-w-40 flex-1 rounded-lg border border-border-strong bg-surface px-2 text-sm">
          <option value="">انتخاب ماده…</option>
          {ingredients.map((i) => <option key={i.id} value={i.id}>{i.name}</option>)}
        </select>
        <input aria-label="مقدار" inputMode="decimal" dir="ltr" value={r.quantity} disabled={readOnly} onChange={(e) => onChange({ quantity: e.target.value })}
          className="h-10 w-24 rounded-lg border border-border-strong bg-surface px-2 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
        <select aria-label="واحد" value={r.unit || units[0]?.value || ''} disabled={readOnly || !ing} onChange={(e) => onChange({ unit: e.target.value })}
          className="h-10 w-28 rounded-lg border border-border-strong bg-surface px-2 text-sm">
          {ing ? [...units].reverse().map((u) => <option key={u.value} value={u.value}>{u.label}</option>) : <option value="">—</option>}
        </select>
        <span className="tabular w-24 text-end text-xs text-text-muted">{formatMoney(rowCost(r))}</span>
        {!readOnly ? <button type="button" onClick={onRemove} aria-label="حذف" className="flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-danger-soft hover:text-danger"><Trash2 className="size-4" /></button> : null}
      </div>
    );
  };

  if (ingredients.length === 0) {
    return (
      <Card>
        <CardHeader icon={<ChefHat />} title="دستور پخت و بهای تمام‌شده" />
        <p className="px-5 pb-5 text-sm text-text-muted">اول در «انبار» مواد اولیه را تعریف کنید؛ بعد اینجا مشخص کنید هر آیتم از هر ماده چقدر مصرف می‌کند.</p>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader icon={<ChefHat />} title="دستور پخت و بهای تمام‌شده" description="با هر فروش، همین مقادیر از موجودی کم و هزینه‌ی آیتم ثبت می‌شود." />
      <div className="flex flex-col gap-5 px-5 pb-5">
        {recipe.variants.length > 1 ? (
          <div role="tablist" aria-label="اندازه" className="flex gap-1 self-start rounded-full bg-surface-muted p-1">
            {recipe.variants.map((v, i) => (
              <button key={v.variant_id} type="button" role="tab" aria-selected={i === tab} onClick={() => setTab(i)}
                className={cx('rounded-full px-3.5 py-1.5 text-sm', i === tab ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted')}>{v.name ?? 'معمولی'}</button>
            ))}
          </div>
        ) : null}

        {variant ? (
          <div className="grid gap-5 lg:grid-cols-[1fr_16rem]">
            <div className="flex flex-col gap-2">
              {rows.map((r) => rowEditor(r, (patch) => setRows(variant.variant_id, rows.map((x) => (x.key === r.key ? { ...x, ...patch } : x))), () => setRows(variant.variant_id, rows.filter((x) => x.key !== r.key))))}
              {!readOnly ? <Button variant="ghost" size="sm" icon={<Plus />} className="self-start" onClick={() => setRows(variant.variant_id, [...rows, { key: newKey(), ingredient_id: '', quantity: '', unit: '' }])}>ماده</Button> : null}
              {rows.length === 0 ? <p className="text-sm text-text-muted">هنوز ماده‌ای برای این اندازه ثبت نشده.</p> : null}
            </div>
            <dl className="flex flex-col gap-2 rounded-2xl bg-surface-muted p-4 text-sm">
              <div className="flex justify-between"><dt className="text-text-muted">قیمت فروش</dt><dd className="tabular">{formatMoney(variant.price)}</dd></div>
              <div className="flex justify-between"><dt className="text-text-muted">بهای مواد</dt><dd className="tabular">{formatMoney(cost)}</dd></div>
              <div className="flex justify-between border-t border-border pt-2"><dt className="font-medium">سود ناخالص</dt><dd className="tabular font-bold text-success">{formatMoney(variant.price - cost)}</dd></div>
              {ratio !== null ? (
                <div className="mt-1">
                  <div className="flex justify-between text-xs"><span className="text-text-muted">فود کاست</span><span className={cx('tabular font-semibold', ratio > FOOD_COST_WARNING ? 'text-warning' : 'text-text')}>{formatPercent(ratio)}</span></div>
                  <div className="mt-1 h-2 overflow-hidden rounded-full bg-surface" role="meter" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(ratio * 100)} aria-label="سهم مواد از قیمت">
                    <div className={cx('h-full rounded-full', ratio > FOOD_COST_WARNING ? 'bg-warning' : 'bg-brand')} style={{ width: `${Math.min(100, ratio * 100)}%` }} />
                  </div>
                  {ratio > FOOD_COST_WARNING ? <p className="mt-2 flex items-start gap-1.5 text-xs text-warning"><TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />بیش از {formatPercent(FOOD_COST_WARNING)} قیمت صرف مواد می‌شود؛ قیمت یا مقدار را بازبینی کنید.</p> : null}
                </div>
              ) : null}
            </dl>
          </div>
        ) : null}

        {recipe.modifiers.length ? (
          <details className="rounded-2xl border border-border">
            <summary className="cursor-pointer px-4 py-3 text-sm font-semibold">اثر افزودنی‌ها روی مواد <span className="font-normal text-text-muted">(مثلاً شیر بادام: + شیر بادام، − شیر)</span></summary>
            <div className="flex flex-col gap-4 border-t border-border p-4">
              {recipe.modifiers.map((m) => {
                const state = modifiers[m.modifier_id] ?? { rows: [], signs: {} };
                const setState = (next: typeof state) => setModifiers((all) => ({ ...all, [m.modifier_id]: next }));

                return (
                  <div key={m.modifier_id} className="flex flex-col gap-2">
                    <p className="text-sm font-medium">{m.name} <span className="text-xs font-normal text-text-muted">• {m.group}</span></p>
                    {state.rows.map((r) => rowEditor(r,
                      (patch) => setState({ ...state, rows: state.rows.map((x) => (x.key === r.key ? { ...x, ...patch } : x)) }),
                      () => setState({ ...state, rows: state.rows.filter((x) => x.key !== r.key) }),
                      { value: state.signs[r.key] ?? 1, toggle: () => setState({ ...state, signs: { ...state.signs, [r.key]: (state.signs[r.key] ?? 1) > 0 ? -1 : 1 } }) }))}
                    {!readOnly ? <Button variant="ghost" size="sm" icon={<Plus />} className="self-start" onClick={() => { const key = newKey(); setState({ rows: [...state.rows, { key, ingredient_id: '', quantity: '', unit: '' }], signs: { ...state.signs, [key]: 1 } }); }}>ماده</Button> : null}
                  </div>
                );
              })}
            </div>
          </details>
        ) : null}

        {message ? <Alert tone={message.ok ? 'success' : 'danger'}>{message.text}</Alert> : null}
        {!readOnly ? <Button className="self-start" onClick={save} loading={pending}>ذخیره‌ی دستور پخت</Button> : null}
      </div>
    </Card>
  );
}
