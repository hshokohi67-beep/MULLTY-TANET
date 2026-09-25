/** Public marketplace shapes (`/public/marketplace/*`). Prices are integer rial. */

export interface Labelled { key: string; label: string }
export interface Services { dine_in: boolean; takeaway: boolean; delivery: boolean; online_payment: boolean; preorder: boolean }

export interface StoreCard {
  store: string; branch: string; name: string; branch_name: string | null; headline: string | null; city: string;
  categories: Labelled[]; amenities: Labelled[]; price_level: number | null;
  logo_url: string | null; cover_url: string | null; image_url: string | null; primary_color: string | null;
  services: Services; is_featured: boolean; distance_km: number | null; is_open: boolean; next_opening_at: string | null;
}

export interface StoreProfile {
  store: string; name: string; headline: string | null; about: string | null;
  categories: Labelled[]; amenities: Labelled[]; price_level: number | null;
  logo_url: string | null; cover_url: string | null; primary_color: string | null; is_featured: boolean; services: Services;
  highlights: { name: string; price_from: number | null; image_url: string | null }[];
  storefront_path: string;
  branches: {
    slug: string; name: string; city: string; province: string | null; address: string | null; phone: string | null;
    latitude: number | null; longitude: number | null; services: Services; is_open: boolean; next_opening_at: string | null;
    hours: { weekday: number; label: string; opens_at: string; closes_at: string }[];
  }[];
}

export interface MarketplaceHome {
  featured: StoreCard[]; popular: StoreCard[]; newest: StoreCard[];
  cities: { city: string; count: number }[];
  categories: (Labelled & { count: number })[];
  amenities: Labelled[];
  total: number;
}

export interface StoreResults { data: StoreCard[]; meta: { page: number; per_page: number; total: number; last_page: number } }

export const PRICE_LABELS: Record<number, string> = { 1: 'اقتصادی', 2: 'متوسط', 3: 'بالا', 4: 'لوکس' };
