'use server';

import { headers } from 'next/headers';
import { api } from '@/lib/api';
import type { Suggestions } from '@/lib/marketplace-types';

const EMPTY: Suggestions = { stores: [], places: [], categories: [], dishes: [] };

/** Type-ahead for the public search box (no session; the API caps and throttles it). */
export async function suggest(q: string): Promise<Suggestions> {
  const query = q.trim().slice(0, 40);
  if (query.length < 2) return EMPTY;
  try {
    return (await api<{ data: Suggestions }>(`/public/marketplace/suggest?q=${encodeURIComponent(query)}`, { tenant: false, auth: false, revalidate: 60 })).data;
  } catch {
    return EMPTY;
  }
}

const AD_TOKEN = /^[A-Za-z0-9]{8,24}\.[a-z_]{3,24}\.\d{9,11}\.[a-f0-9]{32}$/;

/**
 * An ad was seen (half visible for a second) or clicked. Fire-and-forget: the API verifies the
 * signed token and counts each visitor once, so it needs the visitor's IP and browser, not ours.
 */
export async function trackAd(token: string, type: 'impression' | 'click'): Promise<void> {
  if (!AD_TOKEN.test(token) || (type !== 'impression' && type !== 'click')) return;
  const h = await headers();
  const ip = h.get('x-forwarded-for')?.split(',')[0]?.trim() || h.get('x-real-ip') || undefined;
  const extra: Record<string, string> = { 'User-Agent': (h.get('user-agent') ?? '').slice(0, 200) };
  if (ip) extra['X-Forwarded-For'] = ip;
  try {
    await api('/public/ads/events', { method: 'POST', body: { token, type }, tenant: false, auth: false, headers: extra });
  } catch {
    // Metrics are best-effort; never bother the visitor.
  }
}
