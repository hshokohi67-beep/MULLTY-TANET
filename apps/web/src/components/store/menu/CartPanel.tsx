'use client';

import Link from 'next/link';
import { useTransition } from 'react';
import { Minus, Plus, ShoppingBag } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { updateCartLine } from '@/app/actions/storefront';
import { suggestionsFor } from '@/lib/menu-logic';
import type { Menu, MenuProduct } from '@/lib/storefront-types';
import { useStore } from '../StoreProvider';
import { Suggestions } from './Suggestions';

/**
 * The cart beside the menu on wide screens (the floating cart bar hides there): lines with a
 * quantity stepper, the subtotal, what usually goes with this order, and the way to checkout.
 */
export function CartPanel({ menu, onOpen }: { menu: Menu; onOpen: (p: MenuProduct) => void }) {
  const { tenant, session, setCart, announce } = useStore();
  const [pending, start] = useTransition();
  const cart = session?.cart;
  const lines = cart?.quote.lines ?? [];
  const count = lines.reduce((n, l) => n + l.quantity, 0);
  const suggestions = count ? suggestionsFor(menu, lines.map((l) => l.product_id)) : [];

  const change = (ref: string, quantity: number) => start(async () => {
    const result = await updateCartLine(tenant, ref, quantity);
    if (result.ok) setCart(result.data); else announce(result.message, 'error');
  });

  return (
    <aside data-cart-panel aria-label="سبد خرید" className="flex flex-col gap-4 rounded-3xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)]">
      <h2 className="flex items-center gap-2 font-bold">
        <span className="flex size-9 items-center justify-center rounded-full bg-brand-soft text-brand"><ShoppingBag className="size-4" aria-hidden="true" /></span>
        سبد خرید
        {count ? <span className="ms-auto rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-text-muted">{formatNumber(count)} مورد</span> : null}
      </h2>

      {count === 0 ? (
        <p className="rounded-2xl bg-surface-muted px-4 py-6 text-center text-sm leading-7 text-text-muted">سبد هنوز خالی است.<br />با «+» کنار هر محصول اضافه کنید.</p>
      ) : (
        <>
          <ul className={cx('flex max-h-[45vh] flex-col divide-y divide-border overflow-y-auto', pending && 'opacity-70')}>
            {lines.map((l) => (
              <li key={l.ref} className="flex items-center gap-2 py-2.5">
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold">{l.product_name}{l.variant_name ? <span className="font-normal text-text-muted"> • {l.variant_name}</span> : null}</p>
                  {l.modifiers.length ? <p className="truncate text-xs text-text-muted">{l.modifiers.map((m) => m.name).join('، ')}</p> : null}
                  <p className="tabular text-xs text-text-muted">{formatMoney(l.line_total)}</p>
                </div>
                <div className="flex shrink-0 items-center gap-0.5 rounded-full bg-surface-muted p-0.5">
                  <button type="button" disabled={pending} onClick={() => change(l.ref, l.quantity + 1)} aria-label={`یکی بیشتر ${l.product_name}`} className="flex size-7 items-center justify-center rounded-full hover:bg-surface"><Plus className="size-3.5" /></button>
                  <span className="tabular w-5 text-center text-sm font-bold">{formatNumber(l.quantity)}</span>
                  <button type="button" disabled={pending} onClick={() => change(l.ref, l.quantity - 1)} aria-label={l.quantity === 1 ? `حذف ${l.product_name}` : `یکی کمتر ${l.product_name}`} className="flex size-7 items-center justify-center rounded-full hover:bg-surface"><Minus className="size-3.5" /></button>
                </div>
              </li>
            ))}
          </ul>
          <div className="flex items-center justify-between border-t border-border pt-3 text-sm">
            <span className="text-text-muted">جمع سبد</span>
            <span className="tabular text-base font-bold">{formatMoney(cart?.quote.subtotal ?? 0)}</span>
          </div>
          <Link href={`/s/${tenant}/cart`} className="flex h-12 items-center justify-center rounded-2xl bg-brand font-bold text-on-brand shadow-[var(--shadow-sm)] hover:bg-brand-strong">ادامه و ثبت سفارش</Link>
          <Suggestions title="معمولاً با این سفارش می‌گیرند" products={suggestions} branchId={menu.branch.id} onOpen={onOpen} />
        </>
      )}
    </aside>
  );
}
