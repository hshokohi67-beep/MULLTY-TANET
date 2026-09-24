'use client';

import { useMemo, useState, useTransition } from 'react';
import { Check, Minus, Plus } from 'lucide-react';
import { Button, cx } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { addToCart } from '@/app/actions/storefront';
import type { MenuModifierGroup, MenuProduct } from '@/lib/storefront-types';
import { useStore } from './StoreProvider';

/** max_select 0 means "no limit" (same rule as the server's pricer). */
const maxOf = (g: MenuModifierGroup) => (g.max_select > 0 ? g.max_select : g.modifiers.length);

function groupRule(g: MenuModifierGroup): string {
  if (g.max_select === 0) return g.min_select > 0 ? `حداقل ${formatNumber(g.min_select)} گزینه` : 'اختیاری';
  if (g.min_select > 0 && g.max_select === g.min_select) return g.min_select === 1 ? 'یک گزینه انتخاب کنید' : `${formatNumber(g.min_select)} گزینه انتخاب کنید`;
  if (g.min_select > 0) return `حداقل ${formatNumber(g.min_select)} و حداکثر ${formatNumber(g.max_select)} گزینه`;
  return g.max_select === 1 ? 'اختیاری' : `اختیاری • تا ${formatNumber(g.max_select)} گزینه`;
}

/**
 * Size, add-ons and quantity for one product, with the café's min/max rules enforced as you pick
 * (the server checks them again). The price updates live; "add" stays disabled until it's valid.
 */
export function ProductOptions({ product, branchId, onAdded }: { product: MenuProduct; branchId: string; onAdded?: () => void }) {
  const { tenant, setCart, announce } = useStore();
  const [variantId, setVariantId] = useState(product.variants[0]?.id ?? '');
  const [picked, setPicked] = useState<Record<string, string[]>>(() => Object.fromEntries(
    product.modifier_groups.map((g) => [g.id, g.modifiers.filter((m) => m.is_default).slice(0, maxOf(g)).map((m) => m.id)]),
  ));
  const [quantity, setQuantity] = useState(1);
  const [note, setNote] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [pending, start] = useTransition();

  const variant = product.variants.find((v) => v.id === variantId) ?? product.variants[0];
  const extras = useMemo(() => product.modifier_groups.flatMap((g) => g.modifiers.filter((m) => picked[g.id]?.includes(m.id))).reduce((s, m) => s + m.price_delta, 0), [picked, product.modifier_groups]);
  const unmet = product.modifier_groups.filter((g) => (picked[g.id]?.length ?? 0) < g.min_select);
  const total = ((variant?.price ?? 0) + extras) * quantity;

  const toggle = (g: MenuModifierGroup, id: string) => setPicked((prev) => {
    const current = prev[g.id] ?? [];
    if (g.max_select === 1) return { ...prev, [g.id]: current.includes(id) && g.min_select === 0 ? [] : [id] };
    if (current.includes(id)) return { ...prev, [g.id]: current.filter((x) => x !== id) };
    if (current.length >= maxOf(g)) return prev;
    return { ...prev, [g.id]: [...current, id] };
  });

  const add = () => start(async () => {
    setError(null);
    const result = await addToCart(tenant, { branchId, variantId: variant.id, quantity, modifierIds: Object.values(picked).flat(), note });
    if (!result.ok) {
      setError(result.message);
      return;
    }
    setCart(result.data);
    announce(`${product.name} به سبد اضافه شد`);
    onAdded?.();
  });

  if (!product.is_available) {
    return <p className="rounded-xl bg-surface-muted px-4 py-3 text-center text-sm text-text-muted">این آیتم فعلاً موجود نیست.</p>;
  }

  return (
    <div className="flex flex-col gap-5">
      {product.variants.length > 1 ? (
        <fieldset>
          <legend className="mb-2 text-sm font-semibold">اندازه</legend>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
            {product.variants.map((v) => (
              <label key={v.id} className={cx(
                'flex cursor-pointer flex-col rounded-xl border px-3 py-2.5 transition-colors has-[:focus-visible]:shadow-[var(--focus-ring)]',
                v.id === variant.id ? 'border-brand bg-brand-soft' : 'border-border hover:border-border-strong',
              )}>
                <input type="radio" name="variant" value={v.id} checked={v.id === variant.id} onChange={() => setVariantId(v.id)} className="sr-only" />
                <span className="font-medium">{v.name ?? 'معمولی'}</span>
                <span className="tabular text-sm text-text-muted">{formatMoney(v.price)}</span>
              </label>
            ))}
          </div>
        </fieldset>
      ) : null}

      {product.modifier_groups.map((g) => {
        const chosen = picked[g.id] ?? [];
        const single = g.max_select === 1;

        return (
          <fieldset key={g.id}>
            <legend className="mb-2 flex w-full items-center justify-between gap-2">
              <span className="text-sm font-semibold">{g.name}</span>
              <span className={cx('rounded-full px-2 py-0.5 text-xs', g.min_select > 0 && chosen.length < g.min_select ? 'bg-accent-soft text-accent' : 'bg-surface-muted text-text-muted')}>{groupRule(g)}</span>
            </legend>
            <div className="flex flex-col divide-y divide-border rounded-xl border border-border">
              {g.modifiers.map((m) => {
                const on = chosen.includes(m.id);
                const full = !single && !on && chosen.length >= maxOf(g);

                return (
                  <label key={m.id} className={cx('flex min-h-12 cursor-pointer items-center gap-3 px-3 has-[:focus-visible]:bg-surface-muted', full && 'cursor-not-allowed opacity-50')}>
                    <input type={single ? 'radio' : 'checkbox'} name={`g-${g.id}`} checked={on} disabled={full} onChange={() => toggle(g, m.id)} className="sr-only" />
                    <span aria-hidden="true" className={cx(
                      'flex size-5 shrink-0 items-center justify-center border-2 transition-colors',
                      single ? 'rounded-full' : 'rounded-md',
                      on ? 'border-brand bg-brand text-on-brand' : 'border-border-strong',
                    )}>{on ? <Check className="size-3.5" strokeWidth={3} /> : null}</span>
                    <span className="flex-1 text-sm">{m.name}</span>
                    {m.price_delta ? <span className="tabular text-sm text-text-muted">{m.price_delta > 0 ? '+' : ''}{formatMoney(m.price_delta)}</span> : null}
                  </label>
                );
              })}
            </div>
          </fieldset>
        );
      })}

      <div>
        <label htmlFor={`note-${product.id}`} className="mb-2 block text-sm font-semibold">توضیح <span className="font-normal text-text-muted">(اختیاری)</span></label>
        <input id={`note-${product.id}`} value={note} onChange={(e) => setNote(e.target.value)} maxLength={200} placeholder="مثلاً: کم‌شیرین"
          className="h-11 w-full rounded-xl border border-border-strong bg-surface px-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
      </div>

      {error ? <p role="alert" className="rounded-xl bg-danger-soft px-3 py-2 text-sm text-danger">{error}</p> : null}

      <div className="sticky -bottom-4 -mx-5 -mb-4 flex items-center gap-3 border-t border-border bg-surface px-5 py-3">
        <div className="flex shrink-0 items-center gap-0.5 rounded-full bg-surface-muted p-1">
          <button type="button" onClick={() => setQuantity((q) => Math.min(99, q + 1))} aria-label="یکی بیشتر" className="flex size-9 items-center justify-center rounded-full hover:bg-surface"><Plus className="size-4" /></button>
          <output aria-label="تعداد" className="tabular w-7 text-center font-bold">{formatNumber(quantity)}</output>
          <button type="button" onClick={() => setQuantity((q) => Math.max(1, q - 1))} disabled={quantity <= 1} aria-label="یکی کمتر" className="flex size-9 items-center justify-center rounded-full hover:bg-surface disabled:opacity-40"><Minus className="size-4" /></button>
        </div>
        <Button size="lg" className="min-w-0 flex-1 justify-between gap-2 px-4" onClick={add} loading={pending} disabled={unmet.length > 0}>
          <span className="truncate">{unmet.length > 0 ? `انتخاب ${unmet[0].name}` : <>افزودن<span className="hidden sm:inline"> به سبد</span></>}</span>
          <span className="tabular shrink-0 text-sm">{formatMoney(total)}</span>
        </Button>
      </div>
    </div>
  );
}
