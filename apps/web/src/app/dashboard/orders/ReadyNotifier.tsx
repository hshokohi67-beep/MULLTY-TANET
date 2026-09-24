'use client';

import { useEffect, useRef, useState } from 'react';
import { formatNumber } from '@cafe/locale';

interface ReadyOrder { id: string; number: number; label: string }

function chime() {
  try {
    const ctx = new AudioContext();
    [988, 1319].forEach((freq, i) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      const t = ctx.currentTime + i * 0.16;
      osc.frequency.value = freq;
      gain.gain.setValueAtTime(0.0001, t);
      gain.gain.exponentialRampToValueAtTime(0.2, t + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.4);
      osc.connect(gain).connect(ctx.destination);
      osc.start(t);
      osc.stop(t + 0.45);
    });
    setTimeout(() => void ctx.close(), 1200);
  } catch {
    // audio unavailable: the toast still shows
  }
}

/**
 * Tells the counter/waiter the moment the kitchen finishes an order: a toast (and a soft chime,
 * if enabled) for every order that newly appears in the «آماده» column between refreshes.
 */
export function ReadyNotifier({ ready }: { ready: ReadyOrder[] }) {
  const seen = useRef<Set<string> | null>(null);
  const [toasts, setToasts] = useState<ReadyOrder[]>([]);
  const [sound, setSound] = useState(true);

  // Read the saved preference after hydration (the server can't know it).
  useEffect(() => {
    const timer = setTimeout(() => {
      try {
        setSound(localStorage.getItem('orders-ready-sound') !== 'off');
      } catch {
        // storage unavailable: keep the default
      }
    }, 0);

    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    const ids = new Set(ready.map((o) => o.id));

    if (seen.current) {
      const fresh = ready.filter((o) => !seen.current?.has(o.id));
      if (fresh.length > 0) {
        const show = setTimeout(() => setToasts((t) => [...fresh, ...t].slice(0, 4)), 0);
        const hide = setTimeout(() => setToasts((t) => t.filter((x) => !fresh.some((f) => f.id === x.id))), 9000);
        if (sound) chime();
        seen.current = ids;

        return () => {
          clearTimeout(show);
          clearTimeout(hide);
        };
      }
    }

    seen.current = ids;
  }, [ready, sound]);

  const toggle = () => {
    const next = !sound;
    setSound(next);
    try {
      localStorage.setItem('orders-ready-sound', next ? 'on' : 'off');
    } catch {
      // private mode: the choice lasts for this page only
    }
    if (next) chime();
  };

  return (
    <>
      <button
        type="button"
        onClick={toggle}
        aria-pressed={sound}
        className={`h-9 rounded-md px-3 text-sm ${sound ? 'bg-brand-soft text-brand' : 'bg-surface-muted text-text-muted'}`}
        title="صدای اعلان سفارش آماده"
      >
        {sound ? 'صدای «آماده» روشن' : 'صدای «آماده» خاموش'}
      </button>
      <div aria-live="polite" className="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4">
        {toasts.map((t) => (
          <div key={t.id} className="ready-toast pointer-events-auto flex items-center gap-3 rounded-lg border border-success bg-surface px-4 py-3 shadow-[var(--shadow-md)]">
            <span className="flex size-8 items-center justify-center rounded-full bg-success-soft text-success" aria-hidden="true">✓</span>
            <span className="text-sm"><strong className="text-base">#{formatNumber(t.number)}</strong> آماده است • {t.label}</span>
          </div>
        ))}
      </div>
    </>
  );
}
