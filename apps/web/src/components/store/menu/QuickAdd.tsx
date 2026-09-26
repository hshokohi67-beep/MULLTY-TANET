'use client';

import { useTransition } from 'react';
import { Minus, Plus, SlidersHorizontal } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatNumber } from '@cafe/locale';
import { addToCart, updateCartLine } from '@/app/actions/storefront';
import type { MenuProduct } from '@/lib/storefront-types';
import { useStore } from '../StoreProvider';

/** A product with one size and no add-ons can go straight into the cart. */
export const quickAddable = (p: MenuProduct) => p.is_available && p.variants.length === 1 && p.modifier_groups.length === 0;

/**
 * The card's add control. A simple product gets «+», and once it's in the cart a − ۱ + stepper, so a
 * second cup needs no sheet; a product with sizes or add-ons opens its options instead.
 */
export function QuickAdd({ product, branchId, onOptions, className }: { product: MenuProduct; branchId: string; onOptions: () => void; className?: string }) {
  const { tenant, session, setCart, announce } = useStore();
  const [pending, start] = useTransition();
  if (!product.is_available) return null;

  const quick = quickAddable(product);
  const line = quick ? session?.cart?.quote.lines.find((l) => l.variant_id === product.variants[0].id && l.modifiers.length === 0 && !l.note) : undefined;

  const run = (job: () => Promise<{ ok: true; data: Parameters<typeof setCart>[0] } | { ok: false; message: string }>, done?: string) => start(async () => {
    const result = await job();
    if (result.ok) {
      setCart(result.data);
      if (done) announce(done);
    } else {
      announce(result.message, 'error');
    }
  });

  const round = 'flex size-10 items-center justify-center rounded-full transition-transform active:scale-90 disabled:opacity-60';

  if (!quick) {
    return (
      <button type="button" onClick={onOptions} aria-label={`انتخاب گزینه‌های ${product.name}`}
        className={cx(round, 'bg-brand text-on-brand shadow-[var(--shadow-md)] ring-4 ring-surface hover:scale-105', className)}>
        <SlidersHorizontal className="size-4" strokeWidth={2.5} aria-hidden="true" />
      </button>
    );
  }

  if (!line) {
    return (
      <button type="button" disabled={pending} aria-label={`افزودن ${product.name} به سبد`}
        onClick={() => run(() => addToCart(tenant, { branchId, variantId: product.variants[0].id, quantity: 1, modifierIds: [] }), `${product.name} به سبد اضافه شد`)}
        className={cx(round, 'bg-brand text-on-brand shadow-[var(--shadow-md)] ring-4 ring-surface hover:scale-105', className)}>
        <Plus className="size-5" strokeWidth={2.75} aria-hidden="true" />
      </button>
    );
  }

  return (
    <div role="group" aria-label={`تعداد ${product.name} در سبد`}
      className={cx('flex items-center gap-0.5 rounded-full bg-brand p-1 text-on-brand shadow-[var(--shadow-md)] ring-4 ring-surface', pending && 'opacity-80', className)}>
      <button type="button" disabled={pending} aria-label="یکی بیشتر" className="flex size-8 items-center justify-center rounded-full hover:bg-on-brand/15"
        onClick={() => run(() => updateCartLine(tenant, line.ref, line.quantity + 1))}>
        <Plus className="size-4" strokeWidth={2.75} aria-hidden="true" />
      </button>
      <output aria-live="polite" className="tabular min-w-5 text-center text-sm font-bold">{formatNumber(line.quantity)}</output>
      <button type="button" disabled={pending} aria-label={line.quantity === 1 ? 'حذف از سبد' : 'یکی کمتر'} className="flex size-8 items-center justify-center rounded-full hover:bg-on-brand/15"
        onClick={() => run(() => updateCartLine(tenant, line.ref, line.quantity - 1), line.quantity === 1 ? `${product.name} از سبد حذف شد` : undefined)}>
        <Minus className="size-4" strokeWidth={2.75} aria-hidden="true" />
      </button>
    </div>
  );
}
