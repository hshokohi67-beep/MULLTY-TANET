/** Public marketplace shapes (`/public/marketplace/*`). Prices are integer rial. */

export interface Labelled { key: string; label: string }
export interface Services { dine_in: boolean; takeaway: boolean; delivery: boolean; online_payment: boolean; preorder: boolean }

export interface StoreCard {
  store: string; branch: string; name: string; branch_name: string | null; headline: string | null; city: string; district: string | null;
  categories: Labelled[]; amenities: Labelled[]; price_level: number | null;
  offer: string | null; free_delivery: boolean; latitude: number | null; longitude: number | null;
  logo_url: string | null; cover_url: string | null; image_url: string | null; primary_color: string | null;
  services: Services; is_featured: boolean; distance_km: number | null; is_open: boolean; next_opening_at: string | null;
  /** Present only on paid «تبلیغ» results. */
  ad?: AdExtra;
}

/** A sponsored result's extras. `token` only feeds the impression/click beacons. */
export interface AdExtra { token: string; headline: string; body: string | null; cta_label: string; href: string }

/** A paid home/city banner (links only to the café's own pages). */
export interface Banner {
  token: string; store: string; name: string; city: string; headline: string; body: string | null; cta_label: string; href: string;
  image_url: string | null; image_small_url: string | null; logo_url: string | null; primary_color: string | null;
}

export interface StoreProfile {
  store: string; name: string; headline: string | null; about: string | null;
  categories: Labelled[]; amenities: Labelled[]; price_level: number | null;
  logo_url: string | null; cover_url: string | null; primary_color: string | null; is_featured: boolean; services: Services;
  offers: string[]; dietary: Labelled[];
  highlights: { name: string; price_from: number | null; image_url: string | null }[];
  storefront_path: string;
  similar: StoreCard[];
  branches: {
    slug: string; name: string; city: string; district: string | null; province: string | null; address: string | null; phone: string | null;
    latitude: number | null; longitude: number | null; services: Services; is_open: boolean; next_opening_at: string | null;
    hours: { weekday: number; label: string; opens_at: string; closes_at: string }[];
  }[];
}

export interface MarketplaceHome {
  banners: Banner[];
  open_now: number;
  featured: StoreCard[]; popular: StoreCard[]; newest: StoreCard[];
  collections: { key: string; title: string; subtitle: string; filter: Record<string, string | string[]>; stores: StoreCard[] }[];
  places: Place[];
  dietary: Labelled[];
  cities: { city: string; count: number }[];
  categories: (Labelled & { count: number })[];
  amenities: Labelled[];
  total: number;
}

export interface StoreResults { data: StoreCard[]; sponsored: StoreCard[]; banners: Banner[]; meta: { page: number; per_page: number; total: number; last_page: number } }

export const PRICE_LABELS: Record<number, string> = { 1: 'اقتصادی', 2: 'متوسط', 3: 'بالا', 4: 'لوکس' };

export interface Place {
  province: string; count: number;
  cities: { city: string; count: number; districts: { district: string; count: number }[] }[];
}

export interface Suggestions {
  stores: { store: string; name: string; city: string; logo_url: string | null }[];
  places: { city: string; district: string | null; label: string }[];
  categories: Labelled[];
  dishes: { name: string; count: number }[];
}
