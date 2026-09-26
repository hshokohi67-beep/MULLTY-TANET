/**
 * Where a café's storefront lives. With `NEXT_PUBLIC_STORE_BASE_DOMAIN` set (e.g. "cafeyar.ir",
 * or "menu.localhost:3765" in development) every café has its own subdomain, `{slug}.{base}`,
 * served by the proxy; without it the storefront stays at `{STOREFRONT_URL}/s/{slug}`.
 * Safe for client and server bundles (public env only).
 */
const BASE = (process.env.NEXT_PUBLIC_STORE_BASE_DOMAIN ?? '').trim().toLowerCase();
const PROTOCOL = process.env.NEXT_PUBLIC_STORE_PROTOCOL === 'http' ? 'http' : 'https';

export const STORE_SUBDOMAINS = BASE !== '';

/** Absolute storefront URL of a café (optionally a sub-path such as "/t/{qr}" or "/cart"). */
export function storeOrigin(slug: string): string {
  return STORE_SUBDOMAINS ? `${PROTOCOL}://${slug}.${BASE}` : `${process.env.STOREFRONT_URL ?? process.env.NEXT_PUBLIC_STOREFRONT_URL ?? 'http://127.0.0.1:3765'}/s/${slug}`;
}

export function storeLink(slug: string, path = ''): string {
  return `${storeOrigin(slug)}${path}`;
}

/** The café slug a request host belongs to ("narenj.cafeyar.ir" → "narenj"), if any. */
export function slugFromHost(host: string | null | undefined): string | null {
  if (!STORE_SUBDOMAINS || !host) return null;
  const h = host.toLowerCase();
  if (!h.endsWith(`.${BASE}`)) return null;
  const slug = h.slice(0, -(BASE.length + 1));

  return /^[a-z0-9-]{2,60}$/.test(slug) && !RESERVED.has(slug) ? slug : null;
}

/** Subdomains that are never cafés. */
const RESERVED = new Set(['www', 'api', 'app', 'admin', 'panel', 'mail', 'static', 'cdn', 'explore']);

/** "/s/{slug}/rest" → that café's own address when subdomains are on (other paths unchanged). */
export function storeHref(path: string): string {
  const m = /^\/s\/([a-z0-9-]{2,60})(\/.*)?$/.exec(path);

  return STORE_SUBDOMAINS && m ? storeLink(m[1], m[2] ?? '') : path;
}
