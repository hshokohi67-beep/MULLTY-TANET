'use client';

import { ChevronLeft, ChevronRight, Coffee, Dumbbell, Flame, Leaf, TriangleAlert } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatNumber } from '@cafe/locale';
import { dealEndsAt } from '@/lib/deals';
import type { MenuProduct } from '@/lib/storefront-types';
import { Countdown } from './deals/Countdown';
import { DealChips, DealPrice } from './deals/DealPrice';
import { useDeal } from './deals/DealsProvider';
import { Suggestions } from './menu/Suggestions';
import { ProductOptions } from './ProductOptions';
import { MoodChip, ProductPhoto } from './ProductVisuals';

/**
 * Photo (edge to edge in the sheet), mood and dietary tags, nutrition tiles (calories only when the
 * café shows them), what goes well with it, then the option picker.
 */
export function ProductDetails({ product, branchId, onAdded, inSheet = false, showCalories = true, suggestions = [], onOpenSuggestion, onPrev, onNext }: {
  product: MenuProduct;
  branchId: string;
  onAdded?: () => void;
  inSheet?: boolean;
  showCalories?: boolean;
  suggestions?: MenuProduct[];
  onOpenSuggestion?: (p: MenuProduct) => void;
  /** Move to the previous / next product of the same section without closing the sheet. */
  onPrev?: () => void;
  onNext?: () => void;
}) {
  const n = product.nutrition;
  const deal = useDeal(product.id);
  const ends = deal ? dealEndsAt(deal) : null;
  const facts = [
    showCalories && n?.calories ? { icon: Flame, value: formatNumber(n.calories), label: 'کالری' } : null,
    n?.caffeine_mg || product.caffeine?.dose ? {
      icon: Coffee,
      value: n?.caffeine_mg ? formatNumber(n.caffeine_mg) : (product.caffeine?.dose ?? ''),
      label: n?.caffeine_mg ? 'میلی‌گرم کافئین' : 'کافئین',
      // «قهوه، ۲ شات» on its own line (a «•» next to a digit reads like a zero).
      sub: [product.caffeine?.source, n?.caffeine_mg ? product.caffeine?.dose : null].filter(Boolean).join('، ') || null,
    } : null,
    n?.protein_g ? { icon: Dumbbell, value: formatNumber(n.protein_g), label: 'گرم پروتئین' } : null,
  ].filter((f) => f !== null);

  return (
    <div data-mood={product.temperature ?? undefined} className="flex flex-col gap-4">
      <div className="relative">
        <ProductPhoto product={product} sizes="hero" priority className={cx('aspect-[4/3]', inSheet ? '-mx-5 -mt-4 w-[calc(100%+2.5rem)] max-w-none rounded-none' : 'w-full rounded-2xl')} />
        {onPrev || onNext ? (
          <div className="pointer-events-none absolute inset-x-0 top-1/2 flex -translate-y-1/2 justify-between px-1">
            {onPrev ? <button type="button" onClick={onPrev} aria-label="محصول قبلی" className="pointer-events-auto flex size-10 items-center justify-center rounded-full bg-surface/90 text-text shadow-[var(--shadow-md)] hover:bg-surface"><ChevronRight className="size-5" /></button> : <span />}
            {onNext ? <button type="button" onClick={onNext} aria-label="محصول بعدی" className="pointer-events-auto flex size-10 items-center justify-center rounded-full bg-surface/90 text-text shadow-[var(--shadow-md)] hover:bg-surface"><ChevronLeft className="size-5" /></button> : <span />}
          </div>
        ) : null}
      </div>
      {product.name_en ? <p dir="ltr" className="-mt-2 text-end text-sm text-text-subtle">{product.name_en}</p> : null}

      {deal?.offer || deal?.special ? (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-2xl bg-danger-soft px-3.5 py-2.5 text-sm">
          <DealChips product={product} deal={deal} />
          {deal.offer ? <span className="font-semibold">{deal.offer.label}</span> : null}
          {ends !== null ? <Countdown endsAt={ends} className="ms-auto text-xs font-bold text-danger" /> : null}
        </div>
      ) : null}

      <div className="flex flex-wrap items-center gap-2">
        <MoodChip mood={product.temperature} />
        {product.dietary_tags.map((t) => (
          <span key={t.key} className="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-[11px] font-semibold text-success"><Leaf className="size-3" aria-hidden="true" />{t.label}</span>
        ))}
        <DealPrice product={product} deal={deal} className="ms-auto text-sm" />
      </div>

      {product.description ? <p className="leading-7 text-text-muted">{product.description}</p> : null}
      {product.ingredients ? <p className="text-sm leading-7"><span className="font-semibold">مواد تشکیل‌دهنده: </span><span className="text-text-muted">{product.ingredients}</span></p> : null}
      {product.allergens?.length ? (
        <p className="flex flex-wrap items-center gap-1.5 rounded-2xl bg-warning-soft px-3.5 py-2.5 text-sm">
          <TriangleAlert className="size-4 shrink-0 text-warning" aria-hidden="true" /><span className="font-semibold">حساسیت‌زا:</span>{product.allergens.map((a) => a.label).join('، ')}
        </p>
      ) : null}

      {facts.length ? (
        <ul className="grid grid-cols-3 gap-2" aria-label="ارزش غذایی">
          {facts.map((f) => (
            <li key={f.label} className="flex flex-col items-center gap-0.5 rounded-2xl bg-surface-muted px-2 py-2.5 text-center">
              <f.icon className="size-4 text-text-subtle" aria-hidden="true" />
              <span className="tabular text-base font-bold">{f.value}</span>
              <span className="text-[11px] text-text-muted">{f.label}</span>
              {'sub' in f && f.sub ? <span className="text-[11px] text-text-subtle">{f.sub}</span> : null}
            </li>
          ))}
        </ul>
      ) : null}

      <Suggestions title="کنارش می‌چسبد" products={suggestions} branchId={branchId} onOpen={onOpenSuggestion} />

      <ProductOptions product={product} branchId={branchId} onAdded={onAdded} />
    </div>
  );
}
