/** Inventory API shapes. Quantities are in the ingredient's base unit (g, ml, pcs); money is rial. */

export type BaseUnit = 'g' | 'ml' | 'pcs';

export interface Ingredient {
  id: string;
  name: string;
  unit: BaseUnit;
  unit_label: string;
  big_unit_label: string;
  pack_label: string | null;
  pack_size: number | null;
  /** rial per 1000 base units */
  avg_cost: number;
  /** rial per kg / litre / piece */
  cost_per_big_unit: number;
  low_stock_threshold: number;
  is_active: boolean;
  stocks: { branch_id: string; quantity: number; is_low: boolean }[];
  total_quantity: number;
  stock_value: number;
  is_low: boolean;
  is_negative: boolean;
}

export interface StockMovement {
  id: string;
  type: 'purchase' | 'sale' | 'sale_reversal' | 'adjustment' | 'waste' | 'count';
  type_label: string;
  ingredient?: { id: string; name: string; unit: BaseUnit };
  branch_id: string;
  quantity: number;
  balance_after: number;
  unit_cost: number | null;
  order_id: string | null;
  purchase_order_id: string | null;
  note: string | null;
  actor_type: string;
  created_at: string;
}

export interface RecipeLine { ingredient_id: string; name: string; unit: BaseUnit; quantity: number; cost: number }

export interface Recipe {
  variants: { variant_id: string; name: string | null; price: number; cost: number; margin: number; food_cost_ratio: number | null; items: RecipeLine[] }[];
  modifiers: { modifier_id: string; group: string; name: string; price_delta: number; items: RecipeLine[] }[];
}

export interface Supplier { id: string; name: string; phone: string | null; notes: string | null; is_active: boolean; owed: number; orders: number }

export interface PurchaseOrder {
  id: string;
  number: number;
  status: 'draft' | 'ordered' | 'received' | 'cancelled';
  status_label: string;
  supplier?: { id: string; name: string };
  branch?: { id: string; name: string };
  expected_on: string | null;
  total: number;
  paid_total: number;
  balance_due: number;
  /** Owed only once something was delivered. */
  has_receipts: boolean;
  note: string | null;
  ordered_at: string | null;
  received_at: string | null;
  created_at: string;
  items?: {
    id: string;
    ingredient: { id: string; name: string; unit: BaseUnit; pack_label: string | null; pack_size: number | null };
    quantity: number;
    received_quantity: number;
    unit_price: number;
    line_total: number;
  }[];
  payments?: { id: string; amount: number; method: string; method_label: string; note: string | null; paid_at: string }[];
}

/** How quantities read best: 1250 g → "۱٫۲۵ کیلوگرم", 300 ml → "۳۰۰ میلی‌لیتر". */
export function formatQty(quantity: number, unit: BaseUnit): string {
  const fa = (n: number, digits = 2) => new Intl.NumberFormat('fa-IR', { maximumFractionDigits: digits }).format(n);
  if (unit === 'pcs') return `${fa(quantity, 1)} عدد`;
  const big = unit === 'g' ? 'کیلوگرم' : 'لیتر';
  const small = unit === 'g' ? 'گرم' : 'میلی‌لیتر';

  return Math.abs(quantity) >= 1000 ? `${fa(quantity / 1000)} ${big}` : `${fa(quantity, 1)} ${small}`;
}

/** Entry units offered for a base unit (value = API unit code). */
export function entryUnits(unit: BaseUnit, pack?: { label: string | null; size: number | null }): { value: string; label: string }[] {
  const base = unit === 'g' ? [{ value: 'kg', label: 'کیلوگرم' }, { value: 'g', label: 'گرم' }]
    : unit === 'ml' ? [{ value: 'l', label: 'لیتر' }, { value: 'ml', label: 'میلی‌لیتر' }]
      : [{ value: 'pcs', label: 'عدد' }];

  return pack?.label && pack.size ? [...base, { value: 'pack', label: pack.label }] : base;
}
