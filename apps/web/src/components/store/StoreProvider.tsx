'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { loadSession, type StoreSession } from '@/app/actions/storefront';
import type { CartView, Storefront } from '@/lib/storefront-types';

interface StoreContextValue {
  tenant: string;
  store: Storefront;
  session: StoreSession | null;
  /** Replace the cart after a mutation (every cart call returns the fresh quote). */
  setCart: (cart: CartView | null) => void;
  reload: () => Promise<void>;
  /** A short polite announcement (screen readers + a small toast). */
  announce: (message: string, tone?: 'success' | 'error') => void;
  bump: number;
}

const StoreContext = createContext<StoreContextValue | null>(null);

export function useStore(): StoreContextValue {
  const value = useContext(StoreContext);
  if (!value) throw new Error('useStore must be used inside <StoreProvider>');

  return value;
}

/**
 * The menu is rendered statically (fast, cacheable, no personal data); the visitor's cart, table
 * and login load here once, through a Server Function that reads the HttpOnly cookies.
 */
export function StoreProvider({ tenant, store, children }: { tenant: string; store: Storefront; children: ReactNode }) {
  const [session, setSession] = useState<StoreSession | null>(null);
  const [toast, setToast] = useState<{ message: string; tone: 'success' | 'error'; id: number } | null>(null);
  const [bump, setBump] = useState(0);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

  const reload = useCallback(async () => {
    try {
      setSession(await loadSession(tenant));
    } catch {
      setSession({ cart: null, table: null, customer: null });
    }
  }, [tenant]);

  useEffect(() => {
    const t = setTimeout(reload, 0);
    // Coming back to the tab (e.g. after paying) refreshes the cart.
    const onShow = () => { if (!document.hidden) void reload(); };
    document.addEventListener('visibilitychange', onShow);

    return () => {
      clearTimeout(t);
      document.removeEventListener('visibilitychange', onShow);
    };
  }, [reload]);

  const setCart = useCallback((cart: CartView | null) => {
    setSession((s) => ({ ...(s ?? { table: null, customer: null }), cart }));
    setBump((b) => b + 1);
  }, []);

  const announce = useCallback((message: string, tone: 'success' | 'error' = 'success') => {
    clearTimeout(timer.current);
    setToast({ message, tone, id: Date.now() });
    timer.current = setTimeout(() => setToast(null), 3200);
  }, []);

  const value = useMemo(() => ({ tenant, store, session, setCart, reload, announce, bump }), [tenant, store, session, setCart, reload, announce, bump]);

  return (
    <StoreContext.Provider value={value}>
      {children}
      <div aria-live="polite" role="status" className="pointer-events-none fixed inset-x-0 top-3 z-50 flex justify-center px-4">
        {toast ? (
          <p key={toast.id} className={`dialog-in pointer-events-auto rounded-full px-4 py-2 text-sm font-medium shadow-[var(--shadow-lg)] ${toast.tone === 'error' ? 'bg-danger text-white' : 'bg-text text-bg'}`}>
            {toast.message}
          </p>
        ) : null}
      </div>
    </StoreContext.Provider>
  );
}
