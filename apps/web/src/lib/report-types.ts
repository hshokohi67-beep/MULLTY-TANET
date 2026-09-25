import { addDays, gregorianToJalali, JALALI_MONTHS, jalaliDaysInMonth, jalaliToGregorian, toPersianDigits } from '@cafe/locale';

/** Report API shapes (`/reports/*`). Amounts are integer rial; dates are tenant-local "YYYY-MM-DD". */

export interface PeriodInfo { from: string; to: string; days: number; from_jalali: string; to_jalali: string }

export interface Totals {
  orders: number; sales: number; discounts: number; refunds: number; cancelled: number; items: number; buyers: number;
  cogs: number; item_lines: number; costed_lines: number; labour: number; expenses: number; waste: number;
  net_sales: number; average: number; daily_average: number; profit: number;
  margin: number | null; prime_cost: number | null; cogs_coverage: number | null;
}

export interface Summary {
  period: PeriodInfo;
  compare: PeriodInfo | null;
  totals: Totals;
  previous: Totals | null;
  series: { unit: 'day' | 'month'; points: { key: string; label: string; value: number; orders: number; reference: number | null }[] };
  channels: { key: string; label: string; orders: number; sales: number }[];
  payments: { key: string; label: string; amount: number }[];
  goal: { daily: number; target: number; ratio: number } | null;
  stale: boolean;
}

export interface ProductRow {
  key: string; product_id: string | null; name: string; category: string; quantity: number; revenue: number;
  cost: number | null; margin: number | null; margin_ratio: number | null; share: number; class: 'A' | 'B' | 'C';
}
export interface ProductsReport { products: ProductRow[]; categories: { name: string; quantity: number; revenue: number; share: number }[]; total_revenue: number; stale: boolean }

export interface HoursReport {
  cells: { day: number; hour: number; sales: number; orders: number }[];
  profile: { hour: number; orders: number; sales: number }[];
  first_hour: number; last_hour: number;
  busiest: { day: number; hour: number; sales: number; orders: number } | null;
  stale: boolean;
}

export interface BranchRow extends Totals { id: string; name: string; share: number }

export interface CustomersReport {
  new: number; buyers: number; returning: number; repeat: number; known_share: number | null; average_spend: number;
  top: { id: string; name: string | null; phone: string | null; orders: number; spend: number }[];
}

export interface InventoryReport {
  consumption: number; sales: number; waste: number; purchases: number;
  waste_items: { id: string; name: string; unit: 'g' | 'ml' | 'pcs'; quantity: number; cost: number; entries: number }[];
  reasons: { note: string; count: number }[];
  stale: boolean;
}

/** Saturday-first weekday labels (index 0 = Saturday), as the API's `day`. */
export const WEEK_DAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

export interface Preset { key: string; label: string; from: string; to: string }

/** Jalali-aware quick ranges ending today (the week starts on Saturday). */
export function presets(today: string): Preset[] {
  const j = gregorianToJalali(today);
  const weekday = (new Date(`${today}T12:00:00Z`).getUTCDay() + 1) % 7; // 0 = Saturday
  const weekStart = addDays(today, -weekday);
  const monthStart = jalaliToGregorian({ year: j.year, month: j.month, day: 1 });
  const prev = j.month === 1 ? { year: j.year - 1, month: 12 } : { year: j.year, month: j.month - 1 };

  return [
    { key: 'today', label: 'امروز', from: today, to: today },
    { key: 'yesterday', label: 'دیروز', from: addDays(today, -1), to: addDays(today, -1) },
    { key: 'week', label: 'این هفته', from: weekStart, to: today },
    { key: 'last_week', label: 'هفته‌ی قبل', from: addDays(weekStart, -7), to: addDays(weekStart, -1) },
    { key: 'month', label: 'این ماه', from: monthStart, to: today },
    { key: 'last_month', label: JALALI_MONTHS[prev.month - 1], from: jalaliToGregorian({ ...prev, day: 1 }), to: jalaliToGregorian({ ...prev, day: jalaliDaysInMonth(prev.year, prev.month) }) },
    { key: '30d', label: '۳۰ روز', from: addDays(today, -29), to: today },
    { key: '90d', label: '۹۰ روز', from: addDays(today, -89), to: today },
    { key: 'year', label: `سال ${toPersianDigits(j.year)}`, from: jalaliToGregorian({ year: j.year, month: 1, day: 1 }), to: today },
  ];
}

/** "۳ مهر" or "۳ مهر ۱۴۰۵" for axis labels and headings. */
export function jalaliShort(date: string, withYear = false): string {
  const j = gregorianToJalali(date);

  return `${toPersianDigits(j.day)} ${JALALI_MONTHS[j.month - 1]}${withYear ? ` ${toPersianDigits(j.year)}` : ''}`;
}

/** Relative change, or null when there is no base to compare with. */
export function change(now: number, before: number | undefined | null): number | null {
  if (before === undefined || before === null || before === 0) return null;

  return now / before - 1;
}
