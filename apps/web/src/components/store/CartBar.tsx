'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { ShoppingBag } from 'lucide-react';
import { formatMoney, formatNumber } from '@cafe/locale';
import { useStore } from './StoreProvider';

/** Floating "view cart" bar on the menu and product pages; hidden where it would be in the way. */
export function CartBar() {
  const { tenant, session, bump } = useStore();
  const pathname = usePathname();
  const cart = session?.cart;
  const count = cart?.quote.lines.reduce((n, l) => n + l.quantity, 0) ?? 0;
  const onMenu = pathname === `/s/${tenant}` || pathname.startsWith(`/s/${tenant}/p/`);

  if (!onMenu || !cart || count === 0) return null;

  return (
    <div className="pointer-events-none fixed inset-x-0 bottom-0 z-30 px-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
      <Link
        key={bump}
        href={`/s/${tenant}/cart`}
        className="glass cart-bump pointer-events-auto mx-auto flex h-16 max-w-md items-center gap-3 rounded-[1.4rem] ps-4 pe-2 text-text"
      >
        <span className="relative flex size-10 items-center justify-center rounded-full bg-brand-soft text-brand">
          <ShoppingBag className="size-5" aria-hidden="true" />
          <span className="absolute -end-1 -top-1 flex size-5 items-center justify-center rounded-full bg-brand text-[11px] font-bold text-on-brand ring-2 ring-surface">{formatNumber(count)}</span>
        </span>
        <span className="flex-1 text-sm font-semibold">سبد خرید</span>
        <span className="flex h-12 items-center gap-2 rounded-2xl bg-brand px-4 font-bold text-on-brand shadow-[var(--shadow-sm)] transition-colors hover:bg-brand-strong">
          <span className="tabular">{formatMoney(cart.quote.subtotal)}</span>
          <span className="text-xs font-medium opacity-80">ادامه</span>
        </span>
      </Link>
    </div>
  );
}
