'use server';

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
