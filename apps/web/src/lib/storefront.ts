import 'server-only';
import { cache } from 'react';
import { cookies, headers } from 'next/headers';
import { api, ApiError } from './api';
import { slugFromHost, storeLink } from './store-links';
import type { PublicLanding } from './landing-types';
import type { Menu, PublicStory, Storefront } from './storefront-types';

/**
 * Storefront BFF helpers. Every token lives in an HttpOnly cookie scoped to /s/{tenant}, so
 * two cafés open in one browser never see each other's cart, login or table.
 */
export const TENANT_SLUG = /^[a-z0-9-]{2,60}$/;

const COOKIES = {
  cart: 'cs_cart',
  customer: 'cs_cust',
  table: 'cs_table',
  tableInfo: 'cs_table_info',
  branch: 'cs_branch',
} as const;

type CookieKind = keyof typeof COOKIES;

const MAX_AGE: Record<CookieKind, number> = {
  cart: 7 * 24 * 3600,
  customer: 60 * 24 * 3600,
  table: 4 * 3600,
  tableInfo: 4 * 3600,
  branch: 180 * 24 * 3600,
};

export function assertTenant(tenant: string): string {
  if (!TENANT_SLUG.test(tenant)) throw new ApiError(404, 'not_found', 'فروشگاه پیدا نشد.');

  return tenant;
}

/**
 * On the café's own subdomain the cookies cover the whole (host-only) site; on the shared host
 * they stay scoped to /s/{tenant}. Either way no other café ever receives them.
 */
async function cookiePath(tenant: string): Promise<string> {
  const h = await headers();

  return cookiePathFor(tenant, h.get('x-forwarded-host') ?? h.get('host'));
}

/** Read the Host itself: server actions on rewritten routes don't always carry proxy-set headers. */
export function cookiePathFor(tenant: string, host: string | null): string {
  assertTenant(tenant);

  return slugFromHost(host) === tenant ? '/' : `/s/${tenant}`;
}

export async function readCookie(tenant: string, kind: CookieKind): Promise<string | undefined> {
  // The browser only sends a path-scoped cookie on that path, but check the value shape anyway.
  const value = (await cookies()).get(COOKIES[kind])?.value;

  return value && value.length <= 200 ? value : undefined;
}

/** Only callable from Server Functions / Route Handlers. */
export async function writeCookie(tenant: string, kind: CookieKind, value: string): Promise<void> {
  (await cookies()).set(COOKIES[kind], value, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    path: await cookiePath(tenant),
    maxAge: MAX_AGE[kind],
  });
}

export async function clearCookie(tenant: string, kind: CookieKind): Promise<void> {
  (await cookies()).set(COOKIES[kind], '', { path: await cookiePath(tenant), maxAge: 0 });
}

/** The visitor's IP, so the API rate-limits each shopper instead of this server. */
async function clientIp(): Promise<string | undefined> {
  const h = await headers();
  const forwarded = h.get('x-forwarded-for')?.split(',')[0]?.trim();

  return forwarded || h.get('x-real-ip') || undefined;
}

interface SfOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  headers?: Record<string, string>;
  /** Attach the customer cookie's token (default true). */
  customer?: boolean;
  cart?: boolean;
  table?: boolean;
}

/** A per-visitor storefront API call (never cached). */
export async function sf<T>(tenant: string, path: string, options: SfOptions = {}): Promise<T> {
  assertTenant(tenant);
  const extra: Record<string, string> = { ...options.headers };
  const ip = await clientIp();
  if (ip) extra['X-Forwarded-For'] = ip;

  if (options.cart) {
    const cart = await readCookie(tenant, 'cart');
    if (cart) extra['X-Cart-Token'] = cart;
  }
  if (options.table) {
    const table = await readCookie(tenant, 'table');
    if (table) extra['X-Table-Session'] = table;
  }

  const bearer = options.customer === false ? undefined : await readCookie(tenant, 'customer');

  return api<T>(path, { method: options.method, body: options.body, auth: false, tenant, headers: extra, bearer });
}

/** The public shell (cached for a minute; no visitor data). */
export const getStorefront = cache(async (tenant: string): Promise<Storefront | null> => {
  if (!TENANT_SLUG.test(tenant)) return null;
  try {
    return (await api<{ data: Storefront }>('/public/storefront', { auth: false, tenant, revalidate: 60 })).data;
  } catch (error) {
    if (error instanceof ApiError && (error.status === 404 || error.status === 403)) return null;
    throw error;
  }
});

/** The public menu of one branch (cached for a minute). */
export const getMenu = cache(async (tenant: string, branchSlug?: string): Promise<Menu | null> => {
  try {
    const query = branchSlug ? `?branch=${encodeURIComponent(branchSlug)}` : '';

    return (await api<{ data: Menu }>(`/public/menu${query}`, { auth: false, tenant, revalidate: 60 })).data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
});

/** The café's published landing page, or null while the menu is its home page (cached a minute). */
export const getLanding = cache(async (tenant: string): Promise<PublicLanding | null> => {
  try {
    return (await api<{ data: PublicLanding }>('/public/landing', { auth: false, tenant, revalidate: 60 })).data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
});

/** Live stories for the ring (cached briefly; views/clicks are counted separately). */
export const getStories = cache(async (tenant: string, branchSlug?: string): Promise<PublicStory[]> => {
  try {
    const query = branchSlug ? `?branch=${encodeURIComponent(branchSlug)}` : '';

    return (await api<{ data: PublicStory[] }>(`/public/stories${query}`, { auth: false, tenant, revalidate: 30 })).data;
  } catch {
    return []; // stories are a nice-to-have: never break the menu over them
  }
});

export function storeUrl(tenant: string, path = ''): string {
  return storeLink(tenant, path);
}
