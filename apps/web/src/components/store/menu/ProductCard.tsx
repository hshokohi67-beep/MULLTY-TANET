'use client';

import type { MouseEvent } from 'react';
import { Flame, TrendingUp } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { caloriesOf } from '@/lib/menu-logic';
import type { MenuLayout, MenuProduct } from '@/lib/storefront-types';
import { DealChips, DealPrice } from '../deals/DealPrice';
import { useDeal } from '../deals/DealsProvider';
import { MoodChip, ProductPhoto } from '../ProductVisuals';
import { QuickAdd } from './QuickAdd';

export const priceLabel = (p: MenuProduct) => (p.variants.length > 1 ? `از ${formatMoney(p.price_from)}` : formatMoney(p.price_from));

/** Name-line chips: an offer / «پیشنهاد امروز», hot/cold, «ویژه», «پرفروش» and (when the café shows them) calories. */
export function ProductChips({ product, popular, showCalories }: { product: MenuProduct; popular: boolean; showCalories: boolean }) {
  const kcal = showCalories ? caloriesOf(product) : null;
  const deal = useDeal(product.id);

  return (
    <>
      <DealChips product={product} deal={deal} />
      <MoodChip mood={product.temperature} />
      {product.is_featured ? <span className="rounded-full bg-accent-soft px-1.5 py-0.5 text-[11px] font-semibold leading-4 text-accent">ویژه</span> : null}
      {popular ? (
        <span className="inline-flex items-center gap-0.5 rounded-full bg-brand px-1.5 py-0.5 text-[11px] font-semibold leading-4 text-on-brand">
          <TrendingUp className="size-3" aria-hidden="true" />پرفروش
        </span>
      ) : null}
      {kcal !== null ? (
        <span className="inline-flex items-center gap-0.5 rounded-full bg-surface-muted px-1.5 py-0.5 text-[11px] font-medium leading-4 text-text-muted">
          <Flame className="size-3 text-text-subtle" aria-hidden="true" />{formatNumber(kcal)} کالری
        </span>
      ) : null}
    </>
  );
}

/**
 * One product in the café's chosen layout: a list row whose photo breaks out of the card, a
 * two-column card with the photo rising out of its top («دو ستونه»), a photo-led grid tile, or a
 * compact text row. The whole card opens the product sheet (and stays a real link for SEO / new
 * tab); the add control sits on top of it. Prices show the menu offer that is on now.
 */
export function ProductCard({ product: p, layout, tenant, branchId, popular, showCalories, onOpen }: {
  product: MenuProduct;
  layout: MenuLayout;
  tenant: string;
  branchId: string;
  popular: boolean;
  showCalories: boolean;
  onOpen: (p: MenuProduct) => void;
}) {
  const deal = useDeal(p.id);
  const href = `/s/${tenant}/p/${encodeURIComponent(p.slug)}`;
  const open = (e: MouseEvent) => { if (!e.metaKey && !e.ctrlKey) { e.preventDefault(); onOpen(p); } };
  const link = 'after:absolute after:inset-0 after:rounded-[inherit] focus-visible:outline-none focus-visible:after:shadow-[var(--focus-ring)]';
  const soldOut = !p.is_available;
  const unavailable = <span className="inline-flex rounded-full bg-surface-muted px-2.5 py-0.5 text-sm text-text-muted">ناموجود</span>;

  if (layout === 'grid') {
    return (
      <li data-mood={p.temperature ?? undefined} className={cx('mood-card group relative flex flex-col overflow-hidden rounded-3xl border border-border', soldOut && 'opacity-60 grayscale-[40%]')}>
        <ProductPhoto product={p} sizes="tile" className="aspect-square w-full" />
        <div className="flex flex-1 flex-col gap-1.5 p-3 pb-3.5">
          <a href={href} onClick={open} className={cx('line-clamp-2 font-bold leading-6', link)}>{p.name}</a>
          <div className="flex flex-wrap gap-1"><ProductChips product={p} popular={popular} showCalories={showCalories} /></div>
          <div className="mt-auto flex items-center justify-between gap-1.5 pt-1">
            {soldOut ? unavailable : <DealPrice product={p} deal={deal} className="text-[13px]" />}
            <QuickAdd product={p} branchId={branchId} onOptions={() => onOpen(p)} className="relative z-10 shrink-0 shadow-none ring-0" />
          </div>
        </div>
      </li>
    );
  }

  if (layout === 'duo') {
    // The photo is bigger than the card's top and rises out of it; the page background rings it.
    return (
      <li data-mood={p.temperature ?? undefined} className={cx('mood-card group relative mt-14 flex flex-col items-center rounded-[1.75rem] border border-border px-3 pb-3 text-center hover:shadow-[var(--shadow-md)]', soldOut && 'opacity-60 grayscale-[40%]')}>
        <ProductPhoto product={p} className="-mt-14 aspect-square w-[82%] rounded-[1.75rem] ring-4 ring-bg transition-transform duration-[var(--duration-slow)] group-hover:-translate-y-1" />
        <a href={href} onClick={open} className={cx('mt-2.5 line-clamp-2 font-bold leading-6', link)}>{p.name}</a>
        <div className="mt-1 flex flex-wrap justify-center gap-1"><ProductChips product={p} popular={popular} showCalories={showCalories} /></div>
        {p.description ? <p className="mt-1 line-clamp-2 text-xs leading-5 text-text-muted">{p.description}</p> : null}
        <div className="mt-auto flex w-full items-center justify-between gap-1.5 pt-2.5">
          {soldOut ? unavailable : <DealPrice product={p} deal={deal} className="text-sm" />}
          <QuickAdd product={p} branchId={branchId} onOptions={() => onOpen(p)} className="relative z-10 shrink-0" />
        </div>
      </li>
    );
  }

  if (layout === 'compact') {
    return (
      <li className={cx('group relative flex items-center gap-3 border-b border-border py-3 last:border-b-0', soldOut && 'opacity-60')}>
        <div className="flex min-w-0 flex-1 flex-col gap-1">
          <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <a href={href} onClick={open} className={cx('font-bold', link)}>{p.name}</a>
            <ProductChips product={p} popular={popular} showCalories={showCalories} />
          </span>
          {p.description ? <span className="line-clamp-1 text-[13px] text-text-muted">{p.description}</span> : null}
        </div>
        <span className="shrink-0 text-sm">{soldOut ? <span className="text-text-muted">ناموجود</span> : <DealPrice product={p} deal={deal} />}</span>
        <QuickAdd product={p} branchId={branchId} onOptions={() => onOpen(p)} className="relative z-10 ring-0 shadow-none" />
      </li>
    );
  }

  // List: the photo is taller than the text box and breaks out of the card's top edge.
  return (
    <li data-mood={p.temperature ?? undefined}
      className={cx('mood-card group relative mt-7 flex gap-3.5 rounded-3xl border border-border p-3 hover:shadow-[var(--shadow-md)]', soldOut && 'opacity-60 grayscale-[40%]')}>
      <div className="flex min-w-0 flex-1 flex-col py-0.5">
        <h3 className="flex flex-wrap items-center gap-x-2 gap-y-1 font-bold leading-7">
          <a href={href} onClick={open} className={link}>{p.name}</a>
          <ProductChips product={p} popular={popular} showCalories={showCalories} />
        </h3>
        {p.description ? <p className="mt-0.5 line-clamp-2 text-[13px] leading-6 text-text-muted">{p.description}</p> : null}
        <p className="mt-auto pt-2">
          {soldOut ? unavailable : <span className="inline-flex rounded-full bg-surface-muted px-2.5 py-0.5 text-sm"><DealPrice product={p} deal={deal} /></span>}
        </p>
      </div>
      <ProductPhoto product={p} className="-mt-9 size-32 shrink-0 self-start rounded-3xl ring-4 ring-bg transition-transform duration-[var(--duration-slow)] group-hover:-translate-y-1" />
      <QuickAdd product={p} branchId={branchId} onOptions={() => onOpen(p)} className="absolute bottom-1.5 end-1.5 z-10" />
    </li>
  );
}
