'use client';

import { ChefHat } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { offerPrice, percentOff, type ProductDeal } from '@/lib/deals';
import type { MenuProduct } from '@/lib/storefront-types';

/** The price line: «از» for several sizes, and with an offer the old price struck through. */
export function DealPrice({ product, deal, className, strongClass = 'font-bold' }: {
  product: MenuProduct;
  deal: ProductDeal | null;
  className?: string;
  strongClass?: string;
}) {
  const from = product.variants.length > 1 ? 'از ' : '';
  const offer = deal?.offer ?? null;

  if (!offer) return <span className={cx('tabular whitespace-nowrap', strongClass, className)}>{from}{formatMoney(product.price_from)}</span>;

  const price = offerPrice(product.price_from, offer);

  return (
    <span className={cx('tabular inline-flex flex-wrap items-baseline gap-x-1.5 whitespace-nowrap', className)}>
      <span className="sr-only">قیمت قبل</span>
      <s className="text-[0.8em] font-normal text-text-subtle decoration-danger/70">{formatMoney(product.price_from)}</s>
      <span className="sr-only">قیمت با تخفیف</span>
      <span className={strongClass}>{from}{formatMoney(price)}</span>
    </span>
  );
}

/** «٪۲۰» and «پیشنهاد امروز» chips (nothing when the product has neither). */
export function DealChips({ product, deal }: { product: MenuProduct; deal: ProductDeal | null }) {
  if (!deal) return null;

  return (
    <>
      {deal.offer ? (
        <span className="rounded-full bg-danger px-1.5 py-0.5 text-[11px] font-bold leading-4 text-on-danger" title={deal.offer.label}>
          {formatNumber(percentOff(product.price_from, deal.offer))}٪ تخفیف
        </span>
      ) : null}
      {deal.special ? (
        <span className="inline-flex items-center gap-0.5 rounded-full bg-warning-soft px-1.5 py-0.5 text-[11px] font-semibold leading-4 text-text">
          <ChefHat className="size-3 text-warning" aria-hidden="true" />پیشنهاد امروز
        </span>
      ) : null}
    </>
  );
}
