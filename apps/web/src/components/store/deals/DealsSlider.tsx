'use client';

import { useEffect, useRef, useState, type ReactNode } from 'react';
import { BadgePercent, ChefHat } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatNumber } from '@cafe/locale';
import { dealEndsAt, dealItems, percentOff } from '@/lib/deals';
import type { Menu, MenuProduct } from '@/lib/storefront-types';
import { ProductPhoto } from '../ProductVisuals';
import { ScrollRow } from '../ScrollRow';
import { Countdown } from './Countdown';
import { DealPrice } from './DealPrice';
import { useDeals } from './DealsProvider';

/**
 * «پیشنهاد امروز و تخفیف‌ها»: what is on now, as photo cards with the discount, the struck price and
 * a live countdown. Swipe on phones, arrows (and the wheel) on desktop; it moves on by itself only
 * when the visitor allows motion, and stops while hovered or focused. Empty = nothing rendered.
 * `open`/`href` decide what a card does (the menu opens its sheet, the landing page links to it).
 */
export function DealsSlider({ menu, title = 'پیشنهاد امروز و تخفیف‌ها', open, href, action, variant = 'slider', className, rowClassName, cardClassName, gridClassName, heading }: {
  menu: Menu;
  title?: string | null;
  open?: (p: MenuProduct) => void;
  href?: (p: MenuProduct) => string;
  /** Extra control on the card (the menu's quick add). */
  action?: (p: MenuProduct) => ReactNode;
  variant?: 'slider' | 'grid';
  className?: string;
  /** Overrides for hosts with their own spacing (the landing page uses container queries). */
  rowClassName?: string;
  cardClassName?: string;
  gridClassName?: string;
  /** A host's own heading instead of the menu's (rendered only when there are items). */
  heading?: ReactNode;
}) {
  const deals = useDeals();
  const items = dealItems(menu, deals);
  const row = useRef<HTMLElement>(null);
  const [paused, setPaused] = useState(false);

  // Autoplay: one card every few seconds, back to the first at the end.
  useEffect(() => {
    if (variant !== 'slider' || paused || items.length < 2) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const id = setInterval(() => {
      const el = row.current;
      if (!el || document.hidden) return;
      const card = el.firstElementChild as HTMLElement | null;
      const step = (card?.offsetWidth ?? 280) + 12;
      const max = el.scrollWidth - el.clientWidth;
      if (max <= 4) return;
      const rtl = getComputedStyle(el).direction === 'rtl';
      const pos = Math.abs(el.scrollLeft);
      el.scrollTo({ left: pos >= max - 4 ? 0 : (rtl ? -1 : 1) * (pos + step), behavior: 'smooth' });
    }, 4500);

    return () => clearInterval(id);
  }, [variant, paused, items.length]);

  if (items.length === 0) return null;

  const cards = items.map(({ product: p, deal }) => {
    const ends = dealEndsAt(deal);
    const body = (
      <>
        <span className="relative block">
          <ProductPhoto product={p} sizes="tile" className="aspect-[4/3] w-full" />
          <span className="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-2.5">
            {deal.offer ? (
              <span className="inline-flex items-baseline gap-0.5 rounded-2xl bg-danger px-2.5 py-1 text-on-danger shadow-[var(--shadow-md)]">
                <span className="text-lg font-black leading-6 tabular">{formatNumber(percentOff(p.price_from, deal.offer))}٪</span>
                <span className="text-[11px] font-semibold">تخفیف</span>
              </span>
            ) : <span />}
            {deal.special ? (
              <span className="glass-light inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold text-on-media">
                <ChefHat className="size-3.5" aria-hidden="true" />پیشنهاد امروز
              </span>
            ) : null}
          </span>
          {ends !== null ? (
            <span className="absolute bottom-2.5 start-2.5 rounded-full">
              <Countdown endsAt={ends} className="glass-light rounded-full px-2.5 py-1 text-xs font-bold text-on-media" />
            </span>
          ) : null}
        </span>
        <span className="flex flex-1 flex-col gap-1 p-3.5">
          <span className="line-clamp-1 font-bold">{p.name}</span>
          {deal.offer ? <span className="line-clamp-1 text-xs text-text-muted">{deal.offer.label}</span>
            : p.description ? <span className="line-clamp-1 text-xs text-text-muted">{p.description}</span> : null}
          <span className="mt-auto pt-1.5"><DealPrice product={p} deal={deal} className="text-[15px]" strongClass="font-black" /></span>
        </span>
      </>
    );
    const cardClass = 'mood-card group relative flex h-full w-full flex-col overflow-hidden rounded-3xl border border-border text-start focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none';

    return (
      <li key={p.id} data-mood={p.temperature ?? undefined}
        className={cx('relative', variant === 'slider' ? (cardClassName ?? 'w-[78%] max-w-[17.5rem] shrink-0 snap-start sm:w-64') : '')}>
        {href ? <a href={href(p)} className={cardClass}>{body}</a>
          : <button type="button" onClick={() => open?.(p)} className={cardClass}>{body}</button>}
        {action ? <span className="absolute bottom-3 end-3 z-10">{action(p)}</span> : null}
      </li>
    );
  });

  const onlySpecials = items.every((i) => !i.deal.offer);
  const Icon = onlySpecials ? ChefHat : BadgePercent;

  return (
    <section aria-labelledby="deals-title" className={className}
      onMouseEnter={() => setPaused(true)} onMouseLeave={() => setPaused(false)} onFocusCapture={() => setPaused(true)} onBlurCapture={() => setPaused(false)}>
      {heading ?? (title ? (
        <h2 id="deals-title" className="mb-3 flex items-center gap-2 text-lg font-bold">
          <span className="flex size-7 items-center justify-center rounded-lg bg-danger text-on-danger"><Icon className="size-4" aria-hidden="true" /></span>
          {title}
          <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-text-muted">{formatNumber(items.length)}</span>
        </h2>
      ) : null)}
      {variant === 'slider' ? (
        <ScrollRow ref={row} as="ul" label={title ?? 'پیشنهادها'} arrowTop="top-[30%]" className={cx('flex snap-x snap-mandatory gap-3 pt-1 pb-3', rowClassName ?? '-mx-4 scroll-px-4 px-4 lg:mx-0 lg:scroll-px-0 lg:px-0')}>
          {cards}
        </ScrollRow>
      ) : (
        <ul className={gridClassName ?? 'grid grid-cols-2 gap-3 sm:grid-cols-3'}>{cards}</ul>
      )}
    </section>
  );
}
