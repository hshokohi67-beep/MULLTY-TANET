'use client';

import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { now, syncClock } from '@/lib/clock';
import { dealsAt, nextChange, type ProductDeal } from '@/lib/deals';
import type { Menu } from '@/lib/storefront-types';

const DealsContext = createContext<Map<string, ProductDeal>>(new Map());

/** The menu offer / «پیشنهاد امروز» on now for a product (null when none). */
export function useDeal(productId: string): ProductDeal | null {
  return useContext(DealsContext).get(productId) ?? null;
}

export function useDeals(): Map<string, ProductDeal> {
  return useContext(DealsContext);
}

/**
 * What is on now, for every card below it. Re-evaluated when the next offer starts or ends (and
 * once the clock has been checked against the server), not every second.
 */
export function DealsProvider({ menu, tenant, sync = true, children }: {
  menu: Menu;
  tenant: string;
  /** Check the clock against the server (off in the panel's preview). */
  sync?: boolean;
  children: ReactNode;
}) {
  const [t, setT] = useState(() => Date.now());

  useEffect(() => {
    if (!sync) return;
    let cancelled = false;
    void syncClock(tenant).then(() => { if (!cancelled) setT(now()); });

    return () => { cancelled = true; };
  }, [tenant, sync]);

  useEffect(() => {
    const next = nextChange(menu, t);
    if (next === null) return;
    // setTimeout caps at ~24.8 days; windows are only a few days out anyway.
    const timer = setTimeout(() => setT(now()), Math.min(next - t + 250, 2 ** 31 - 1));

    return () => clearTimeout(timer);
  }, [menu, t]);

  const deals = useMemo(() => dealsAt(menu, t), [menu, t]);

  return <DealsContext.Provider value={deals}>{children}</DealsContext.Provider>;
}
