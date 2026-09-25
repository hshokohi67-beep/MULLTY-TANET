import 'server-only';
import { api, ApiError } from './api';
import type { MarketplaceHome, StoreProfile, StoreResults } from './marketplace-types';

/** Public marketplace reads (no session, no tenant), cached for a minute like the API allows. */

const opts = { tenant: false, auth: false, revalidate: 60 } as const;

export async function getMarketplaceHome(): Promise<MarketplaceHome> {
  return (await api<{ data: MarketplaceHome }>('/public/marketplace/home', opts)).data;
}

export async function searchStores(params: URLSearchParams): Promise<StoreResults> {
  return api<StoreResults>(`/public/marketplace/stores?${params.toString()}`, opts);
}

export async function getStoreProfile(slug: string): Promise<StoreProfile | null> {
  if (!/^[a-z0-9-]{2,64}$/.test(slug)) return null;
  try {
    return (await api<{ data: StoreProfile }>(`/public/marketplace/stores/${slug}`, opts)).data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
}
