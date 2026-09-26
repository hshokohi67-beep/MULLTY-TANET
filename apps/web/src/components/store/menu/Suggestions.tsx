'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Sparkles } from 'lucide-react';
import { cx } from '@cafe/ui';
import type { MenuProduct } from '@/lib/storefront-types';
import { MoodChip, ProductPhoto } from '../ProductVisuals';
import { useStore } from '../StoreProvider';
import { priceLabel } from './ProductCard';
import { QuickAdd } from './QuickAdd';

/**
 * A short row of products that go well with what the visitor is looking at or ordering: tapping
 * one opens it, «+» adds it at once. Renders nothing without suggestions.
 */
export function Suggestions({ title, products, branchId, onOpen, className }: {
  title: string;
  products: MenuProduct[];
  branchId: string;
  onOpen?: (p: MenuProduct) => void;
  className?: string;
}) {
  const { tenant } = useStore();
  const router = useRouter();
  const page = (p: MenuProduct) => `/s/${tenant}/p/${encodeURIComponent(p.slug)}`;
  if (products.length === 0) return null;

  return (
    <section aria-label={title} className={cx('flex flex-col gap-2.5', className)}>
      <h2 className="flex items-center gap-1.5 text-sm font-bold"><Sparkles className="size-4 text-accent" aria-hidden="true" />{title}</h2>
      <ul className="grid gap-2">
        {products.map((p) => (
          <li key={p.id} data-mood={p.temperature ?? undefined} className="relative flex items-center gap-3 rounded-2xl border border-border bg-surface p-2">
            <ProductPhoto product={p} className="size-14 shrink-0 rounded-xl" />
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
              {onOpen ? (
                <button type="button" onClick={() => onOpen(p)} className="truncate text-start text-sm font-semibold after:absolute after:inset-0 after:rounded-2xl">{p.name}</button>
              ) : (
                <Link href={page(p)} className="truncate text-sm font-semibold after:absolute after:inset-0 after:rounded-2xl">{p.name}</Link>
              )}
              <span className="flex items-center gap-1.5 text-xs text-text-muted"><span className="tabular">{priceLabel(p)}</span><MoodChip mood={p.temperature} /></span>
            </div>
            <QuickAdd product={p} branchId={branchId} onOptions={() => (onOpen ? onOpen(p) : router.push(page(p)))} className="relative z-10 shadow-none ring-0" />
          </li>
        ))}
      </ul>
    </section>
  );
}
