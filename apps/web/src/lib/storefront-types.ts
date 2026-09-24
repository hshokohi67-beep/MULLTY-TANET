import type { Branding } from './types';

/** Shapes of the public storefront API (amounts are integer rial). */

/** Menu mood: hot items glow warm, cold ones cool (null = neutral). */
export type Mood = 'hot' | 'cold';

export interface PublicStory {
  id: string;
  image_url: string;
  thumb_url: string;
  width: number;
  height: number;
  caption: string | null;
  link: { type: 'product'; slug: string } | { type: 'category'; category_id: string } | { type: 'url'; url: string } | null;
  cta_label: string | null;
  starts_at: string;
}

export interface StoreBranch {
  id: string;
  name: string;
  slug: string;
  phone: string | null;
  city: string | null;
  address: string | null;
  latitude: number | null;
  longitude: number | null;
  is_open: boolean;
  next_opening_at: string | null;
  opening_hours: { weekday: number; weekday_label: string; opens_at: string; closes_at: string }[];
  delivery: boolean;
}

export interface Storefront {
  name: string;
  slug: string;
  timezone: string;
  branding: Branding | null;
  contact: { phone: string | null; instagram: string | null };
  branches: StoreBranch[];
  features: { online_payment: boolean; club: boolean; wallet_payments: boolean; preorder_when_closed: boolean };
}

export interface MenuModifier { id: string; name: string; price_delta: number; is_default: boolean }
export interface MenuModifierGroup { id: string; name: string; min_select: number; max_select: number; modifiers: MenuModifier[] }

export interface MenuProduct {
  id: string;
  slug: string;
  name: string;
  description: string | null;
  is_featured: boolean;
  temperature: Mood | null;
  is_available: boolean;
  category_ids: string[];
  price_from: number;
  variants: { id: string; name: string | null; price: number }[];
  images: { url: string; alt: string | null; width: number | null; height: number | null }[];
  nutrition: { calories?: number; caffeine_mg?: number; protein_g?: number } | null;
  dietary_tags: { key: string; label: string }[];
  modifier_groups: MenuModifierGroup[];
}

export interface MenuCategory { id: string; parent_id: string | null; name: string; slug: string; description: string | null; temperature: Mood | null; image_url: string | null }

export interface Menu {
  branch: { id: string; name: string; slug: string };
  categories: MenuCategory[];
  products: MenuProduct[];
  generated_at: string;
}

export interface QuoteLine {
  ref: string;
  product_id: string;
  variant_id: string;
  product_name: string;
  variant_name: string | null;
  unit_price: number;
  modifiers: { modifier_id: string; group_name: string; name: string; price_delta: number }[];
  modifiers_total: number;
  quantity: number;
  line_total: number;
  note: string | null;
  problem: { code: string; message: string } | null;
}

export interface Quote {
  lines: QuoteLine[];
  subtotal: number;
  discount: { name: string; code: string | null; amount: number } | null;
  delivery: { zone_name: string; distance_m: number; fee: number; free_delivery_applied: boolean; free_delivery_min: number | null; min_order: number; eta_minutes: number | null } | null;
  delivery_fee: number;
  total: number;
  scheduled_for: string | null;
  issues: { code: string; message: string }[];
  can_checkout: boolean;
}

export interface CartView {
  order_type: 'takeaway' | 'delivery' | 'qr_table';
  branch: { id: string; name: string };
  table: { label: string } | null;
  expires_at: string;
  quote: Quote;
}

export interface Customer {
  id: string;
  name: string | null;
  phone: string;
  birth_month: number | null;
  birth_day: number | null;
  birthday_locked: boolean;
  referral_code: string | null;
  marketing_opt_in: boolean;
}

export interface CustomerAddress {
  id: string;
  title: string;
  recipient_name: string | null;
  recipient_phone: string | null;
  province: string | null;
  city: string;
  district: string | null;
  address: string;
  postal_code: string | null;
  building_number: string | null;
  floor: string | null;
  unit: string | null;
  latitude: number | null;
  longitude: number | null;
  notes: string | null;
  is_default: boolean;
  has_location: boolean;
}

export interface ClubTier { id: string; name: string; color: string; min_spend: number; points_multiplier: number; perks: string | null }

export interface Club {
  wallet_balance: number;
  points: number;
  points_value: number;
  lifetime_spend: number;
  tier: ClubTier | null;
  next_tier: (ClubTier & { remaining: number }) | null;
  referral_code: string;
  program: {
    enabled: boolean;
    wallet_payments: boolean;
    points_per_100k: number;
    point_value: number;
    min_redeem_points: number;
    birthday_wallet_gift: number;
    birthday_points: number;
    referral_referrer_reward: number;
    referral_referee_reward: number;
  };
}

export interface LedgerRow { id: string; type: string; type_label: string; amount: number; balance_after: number; description: string | null; order_id: string | null; created_at: string }
