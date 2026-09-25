/** Shapes returned by the Laravel API (apps/api). Money is always integer rial. */

export interface ApiErrorBody {
  message: string;
  code: string;
  errors?: Record<string, string[]>;
}

export interface StaffUser {
  id: string;
  name: string;
  email: string | null;
  phone: string | null;
  is_platform_admin: boolean;
}

export interface Membership {
  tenant: { id: string; name: string; slug: string };
  permissions: string[];
  /** Display only (help centre); permissions are the authority. */
  roles?: { key: string; name: string }[];
}

export interface MeResponse {
  user: StaffUser;
  memberships: Membership[];
}

export interface Tenant {
  id: string;
  name: string;
  slug: string;
  status: 'trial' | 'active' | 'suspended' | 'archived';
  status_label: string;
  timezone: string;
  locale: string;
  currency: string;
  display_currency_unit: 'toman' | 'rial';
  created_at: string | null;
}

export interface Branding {
  logo_url: string | null;
  cover_url?: string | null;
  primary_color: string;
  theme: 'light' | 'dark';
  seo_title: string | null;
  seo_description: string | null;
}

export interface OpeningHour {
  weekday: 1 | 2 | 3 | 4 | 5 | 6 | 7;
  weekday_label: string;
  opens_at: string;
  closes_at: string;
  overnight: boolean;
}

export interface Branch {
  id: string;
  name: string;
  slug: string;
  phone: string | null;
  province: string | null;
  district?: string | null;
  city: string | null;
  address: string | null;
  postal_code: string | null;
  latitude: number | null;
  longitude: number | null;
  is_active: boolean;
  opening_hours?: OpeningHour[];
  created_at: string | null;
}

export interface SettingItem {
  key: string;
  label: string;
  type: 'string' | 'bool' | 'int';
  secret: boolean;
  value: string | boolean | number | null;
  is_set: boolean;
  masked: string | null;
}

export interface Role {
  id: string;
  key: string;
  name: string;
  is_system: boolean;
  permissions?: string[];
}

export interface TeamMember {
  id: string;
  status: 'active' | 'disabled';
  user: StaffUser;
  roles: Role[];
  joined_at: string | null;
}

export interface PublicTenant {
  name: string;
  slug: string;
  locale: string;
  timezone: string;
  display_currency_unit: 'toman' | 'rial';
  branding: Branding | null;
}

/** Result of a server action, consumed by useActionState in forms. */
export interface FormState {
  ok: boolean;
  message?: string;
  errors?: Record<string, string>;
}

/* ---------- Catalog (Phase 2). Amounts are integer rial. ---------- */

export interface Category {
  id: string;
  parent_id: string | null;
  name: string;
  slug: string;
  description: string | null;
  sort: number;
  is_active: boolean;
  temperature: 'hot' | 'cold' | null;
  image_url?: string | null;
  products_count?: number;
}

export interface VariantPrice {
  branch_id: string;
  amount: number;
}

export interface Variant {
  id: string;
  name: string | null;
  sku: string | null;
  is_active: boolean;
  base_price: number | null;
  branch_prices: VariantPrice[];
}

export interface ProductImage {
  id: string;
  url: string;
  alt: string | null;
  width: number | null;
  height: number | null;
}

export interface ProductAvailability {
  branch_id: string;
  status: 'available' | 'sold_out' | 'hidden';
  status_label: string;
  sold_out_until: string | null;
}

export interface Product {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  is_active: boolean;
  is_featured: boolean;
  temperature: 'hot' | 'cold' | null;
  sort: number;
  nutrition: { calories?: number; caffeine_mg?: number; protein_g?: number } | null;
  dietary_tags: string[];
  categories?: { id: string; name: string }[];
  variants?: Variant[];
  price_from?: number | null;
  images?: ProductImage[];
  modifier_groups?: { id: string; name: string }[];
  availability?: ProductAvailability[];
}

export interface Modifier {
  id: string;
  name: string;
  price_delta: number;
  is_default: boolean;
  is_active: boolean;
}

export interface ModifierGroup {
  id: string;
  name: string;
  min_select: number;
  max_select: number;
  is_required: boolean;
  sort: number;
  products_count?: number;
  modifiers?: Modifier[];
}

export interface BulkPriceRow {
  variant_id: string;
  product_id: string;
  product: string;
  variant: string | null;
  old_amount: number;
  new_amount: number;
}

export interface BulkPriceResult {
  preview: boolean;
  batch_id: string | null;
  changed_count: number;
  rows: BulkPriceRow[];
}

export const DIETARY_TAGS: Record<string, string> = {
  vegan: 'گیاهی',
  vegetarian: 'گیاه‌خواری',
  gluten_free: 'بدون گلوتن',
  dairy_free: 'بدون لبنیات',
  sugar_free: 'بدون قند',
  low_calorie: 'کم‌کالری',
  high_protein: 'پرپروتئین',
  spicy: 'تند',
  contains_nuts: 'حاوی مغزها',
};

/* ---------- Commerce (Phase 3). Amounts are integer rial. ---------- */

export interface OrderItemModifier {
  group_name: string;
  name: string;
  price_delta: number;
}

export interface OrderItem {
  id: string;
  product_name: string;
  variant_name: string | null;
  unit_price: number;
  modifiers_total: number;
  quantity: number;
  line_total: number;
  note: string | null;
  modifiers: OrderItemModifier[];
}

export interface Order {
  id: string;
  daily_number: number;
  business_date: string;
  branch?: { id: string; name: string };
  table?: { id: string; label: string } | null;
  type: string;
  type_label: string;
  source: string;
  status: string;
  status_label: string;
  next_statuses: { value: string; label: string }[];
  payment_status: string;
  payment_status_label: string;
  scheduled_for: string | null;
  kitchen_release_at?: string | null;
  customer_note: string | null;
  customer_id: string | null;
  contact_name: string | null;
  contact_phone: string | null;
  address: Record<string, string | number | null> | null;
  subtotal: number;
  discount_total: number;
  discount: { name: string; code: string | null } | null;
  delivery_fee: number;
  total: number;
  paid_total: number;
  refunded_total: number;
  remaining_due: number;
  needs_refund: boolean;
  items?: OrderItem[];
  history?: { from: string | null; to: string; to_label: string; note: string | null; at: string }[];
  placed_at: string;
  cancel_reason: string | null;
}

export interface RestaurantTable {
  id: string;
  branch_id: string;
  label: string;
  capacity: number | null;
  is_active: boolean;
  sort: number;
  qr: { hint: string; issued_at: string | null } | null;
  open_session: { id: string; opened_at: string } | null;
}

export interface TableRequestItem {
  id: string;
  type: string;
  type_label: string;
  status: string;
  table: { id: string; label: string; branch_id: string };
  created_at: string;
}

export interface DeliveryZone {
  id: string;
  branch_id: string;
  name: string;
  radius_m: number;
  delivery_fee: number;
  free_delivery_min: number | null;
  min_order: number;
  eta_minutes: number | null;
  is_active: boolean;
}

export interface Discount {
  id: string;
  name: string;
  code: string | null;
  is_automatic: boolean;
  kind: 'percent' | 'fixed';
  kind_label: string;
  value: number;
  applies_to: 'order' | 'items';
  min_order: number;
  max_discount: number | null;
  starts_at: string | null;
  ends_at: string | null;
  schedule: { weekdays?: number[]; from?: string; to?: string } | null;
  rules?: { type: string; target: string }[];
  usage_limit: number | null;
  per_customer_limit: number | null;
  used_count: number;
  is_active: boolean;
}

export interface PaymentRefund {
  id: string;
  amount: number;
  method: string;
  method_label: string;
  reference: string | null;
  reason: string;
  created_at: string;
}

export interface Payment {
  id: string;
  order?: { id: string; daily_number: number; business_date: string; status: string; status_label: string };
  method: 'online' | 'cash' | 'card_pos' | 'other' | 'wallet';
  method_label: string;
  gateway: string | null;
  status: 'pending' | 'paid' | 'failed' | 'expired';
  status_label: string;
  amount: number;
  refunded_amount: number;
  refundable: number;
  ref_id: string | null;
  card_pan: string | null;
  fee: number | null;
  reference: string | null;
  note: string | null;
  failure_code: string | null;
  failure_message: string | null;
  refunds?: PaymentRefund[];
  paid_at: string | null;
  failed_at: string | null;
  created_at: string;
}

export interface PaymentSummary {
  date: string;
  methods: { method: string; label: string; count: number; amount: number; refunded: number }[];
}

/** Result of verifying an online payment after the gateway redirect. */
export interface PaymentResult {
  status: 'pending' | 'paid' | 'failed' | 'expired';
  status_label: string;
  amount: number;
  ref_id: string | null;
  card_pan: string | null;
  paid_at: string | null;
  failure_message: string | null;
  order: { id: string; daily_number: number; status: string; status_label: string; payment_status: string; total: number; remaining_due: number; branch: string; tracking_token: string };
}

export interface SettingsMeta {
  payments: { driver: string; test_mode: boolean };
}

export interface TierSummary {
  id: string;
  name: string;
  color: string;
  min_spend: number;
  points_multiplier: number;
  perks: string | null;
}

export interface ClubSummary {
  wallet_balance: number;
  points: number;
  points_value: number;
  lifetime_spend: number;
  tier: TierSummary | null;
  next_tier: (TierSummary & { remaining: number }) | null;
  referral_code: string;
  program: {
    enabled: boolean;
    wallet_payments: boolean;
    points_per_100k: number;
    point_value: number;
    min_redeem_points: number;
  };
}

export interface CustomerRow {
  id: string;
  name: string | null;
  phone: string;
  birth_month: number | null;
  birth_day: number | null;
  wallet_balance: number;
  points: number;
  lifetime_spend: number;
  tier: { id: string; name: string | null; color: string | null } | null;
  orders_count: number;
  last_order_at: string | null;
  created_at: string;
}

export interface CustomerDetail extends CustomerRow {
  staff_note: string | null;
  birthday_locked: boolean;
  marketing_opt_in: boolean;
  referred_by: { id: string; name: string | null } | null;
  club: ClubSummary;
  recent_orders: Order[];
}

export interface LedgerRow {
  id: string;
  type: string;
  type_label: string;
  amount: number;
  balance_after: number;
  description: string | null;
  order_id: string | null;
  actor_type: string | null;
  created_at: string;
}

export interface CashbackRuleItem {
  id: string;
  name: string;
  category_id: string | null;
  min_spend: number;
  kind: 'fixed' | 'percent';
  value: number;
  max_reward: number | null;
  is_active: boolean;
}

export interface LoyaltyProgram {
  settings: Record<string, number | boolean>;
  tiers: (TierSummary & { members: number })[];
  cashback_rules: CashbackRuleItem[];
}

export interface KdsItem {
  id: string;
  station_id: string;
  station: string;
  name: string;
  variant: string | null;
  quantity: number;
  modifiers: string[];
  note: string | null;
  status: 'queued' | 'preparing' | 'ready' | 'cancelled';
  started_at: string | null;
  ready_at: string | null;
}

export interface KdsOrder {
  order_id: string;
  daily_number: number;
  type: string;
  type_label: string;
  table: string | null;
  customer_name: string | null;
  tier: string | null;
  note: string | null;
  scheduled_for: string | null;
  placed_at: string;
  order_status: string;
  state: 'open' | 'done' | 'cancelled';
  items: KdsItem[];
}

export interface KdsBoard {
  stations: { id: string; name: string; late_after_minutes: number }[];
  orders: KdsOrder[];
  table_requests: { id: string; type: string; type_label: string; table: string; created_at: string }[];
  branch_id: string;
  server_time: string;
}

export interface KdsMe {
  actor: { type: 'device' | 'user'; name: string | null; station_id: string | null };
  branches: { id: string; name: string; stations: { id: string; name: string; late_after_minutes: number }[] }[];
}

export interface KitchenSetup {
  stations: { id: string; branch_id: string; name: string; is_default: boolean; late_after_minutes: number; is_active: boolean; sort: number; product_ids: string[] }[];
  devices: { id: string; name: string; branch_id: string; station_id: string | null; status: 'waiting' | 'paired' | 'revoked'; pairing_expires_at: string | null; paired_at: string | null; last_seen_at: string | null }[];
}
