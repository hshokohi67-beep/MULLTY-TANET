'use client';

import { useRouter } from 'next/navigation';
import { useEffect } from 'react';

/**
 * Keeps a server-rendered board fresh without re-rendering it blindly: every few seconds it asks
 * a tiny version endpoint (a 304 with no body when nothing changed) and refreshes the page only
 * when the version moved. Paused while the tab is hidden; checks immediately when it returns.
 */
export function LiveRefresh({ endpoint, seconds = 10 }: { endpoint: string; seconds?: number }) {
  const router = useRouter();

  useEffect(() => {
    let etag: string | null = null;
    let timer: ReturnType<typeof setInterval> | undefined;

    const check = async () => {
      try {
        const response = await fetch(endpoint, { headers: etag ? { 'If-None-Match': etag } : {}, cache: 'no-store' });
        if (response.status !== 200) return;

        const next = response.headers.get('ETag');
        if (etag !== null && next !== etag) router.refresh();
        etag = next;
      } catch {
        // offline for a moment: try again on the next tick
      }
    };

    const start = () => {
      timer ??= setInterval(() => void check(), seconds * 1000);
    };
    const stop = () => {
      if (timer) clearInterval(timer);
      timer = undefined;
    };
    const onVisibility = () => {
      if (document.hidden) {
        stop();
      } else {
        void check();
        start();
      }
    };

    const first = setTimeout(() => void check(), 0);
    start();
    document.addEventListener('visibilitychange', onVisibility);

    return () => {
      clearTimeout(first);
      stop();
      document.removeEventListener('visibilitychange', onVisibility);
    };
  }, [endpoint, router, seconds]);

  return null;
}
