'use client';

import { Coffee, Dumbbell, Flame, Leaf } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import type { MenuProduct } from '@/lib/storefront-types';
import { ProductOptions } from './ProductOptions';
import { MoodChip, ProductPhoto } from './ProductVisuals';

/** Photo (edge to edge in the sheet), mood and dietary tags, nutrition tiles, then the option picker. */
export function ProductDetails({ product, branchId, onAdded, inSheet = false }: { product: MenuProduct; branchId: string; onAdded?: () => void; inSheet?: boolean }) {
  const n = product.nutrition;
  const facts = [
    n?.calories ? { icon: Flame, value: formatNumber(n.calories), label: 'کالری' } : null,
    n?.caffeine_mg ? { icon: Coffee, value: formatNumber(n.caffeine_mg), label: 'میلی‌گرم کافئین' } : null,
    n?.protein_g ? { icon: Dumbbell, value: formatNumber(n.protein_g), label: 'گرم پروتئین' } : null,
  ].filter((f) => f !== null);

  return (
    <div data-mood={product.temperature ?? undefined} className="flex flex-col gap-4">
      <ProductPhoto product={product} sizes="hero" priority className={cx('aspect-[4/3]', inSheet ? '-mx-5 -mt-4 w-[calc(100%+2.5rem)] max-w-none rounded-none' : 'w-full rounded-2xl')} />

      <div className="flex flex-wrap items-center gap-2">
        <MoodChip mood={product.temperature} />
        {product.dietary_tags.map((t) => (
          <span key={t.key} className="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-[11px] font-semibold text-success"><Leaf className="size-3" aria-hidden="true" />{t.label}</span>
        ))}
        <span className="tabular ms-auto text-sm font-bold">{product.variants.length > 1 ? `از ${formatMoney(product.price_from)}` : formatMoney(product.price_from)}</span>
      </div>

      {product.description ? <p className="leading-7 text-text-muted">{product.description}</p> : null}

      {facts.length ? (
        <ul className="grid grid-cols-3 gap-2" aria-label="ارزش غذایی">
          {facts.map((f) => (
            <li key={f.label} className="flex flex-col items-center gap-0.5 rounded-2xl bg-surface-muted px-2 py-2.5 text-center">
              <f.icon className="size-4 text-text-subtle" aria-hidden="true" />
              <span className="tabular text-base font-bold">{f.value}</span>
              <span className="text-[11px] text-text-muted">{f.label}</span>
            </li>
          ))}
        </ul>
      ) : null}

      <ProductOptions product={product} branchId={branchId} onAdded={onAdded} />
    </div>
  );
}
