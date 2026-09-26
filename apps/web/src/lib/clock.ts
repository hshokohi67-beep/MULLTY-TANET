'use client';

/**
 * The visitor's clock, corrected once per page load against the web server's (`/clock`), so an
 * offer's countdown ends when the cart stops giving the discount. Falls back to the local clock.
 */
let offset = 0;
let pending: Promise<void> | null = null;

export const now = () => Date.now() + offset;

export function syncClock(tenant: string): Promise<void> {
  pending ??= (async () => {
    try {
      // On a café subdomain the proxy maps "/clock" to "/s/{tenant}/clock"; elsewhere use the full path.
      const url = window.location.pathname.startsWith(`/s/${tenant}`) ? `/s/${tenant}/clock` : '/clock';
      const sent = Date.now();
      const res = await fetch(url, { cache: 'no-store' });
      const received = Date.now();
      const body = (await res.json()) as { now?: number };
      // Only trust a quick answer: the round trip bounds the error.
      if (typeof body.now === 'number' && received - sent < 3000) offset = body.now - (sent + received) / 2;
    } catch {
      offset = 0;
    }
  })();

  return pending;
}
