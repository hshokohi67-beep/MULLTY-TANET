import { normalizeForSearch } from '@cafe/locale';
import type { Menu, MenuProduct, Mood } from './storefront-types';

/**
 * Menu search, filters, sorting and "goes well with" suggestions: pure functions over the cached
 * public menu, shared by the storefront (client) and server actions. Nothing here calls the API.
 */

export type CalorieRange = 'any' | 'u200' | '200_400' | 'o400' | 'max';
export type MenuSort = 'suggested' | 'cheap' | 'expensive' | 'light';

export interface MenuFilters {
  calories: CalorieRange;
  /** Upper limit for the "max" range (kcal). */
  maxCalories: number | null;
  moods: Mood[];
  tags: string[];
  /** Upper price limit (rial), or null. */
  maxPrice: number | null;
  availableOnly: boolean;
  sort: MenuSort;
}

export const NO_FILTERS: MenuFilters = { calories: 'any', maxCalories: null, moods: [], tags: [], maxPrice: null, availableOnly: false, sort: 'suggested' };

export const CALORIE_LABELS: Record<Exclude<CalorieRange, 'any' | 'max'>, string> = { u200: 'تا ۲۰۰', '200_400': '۲۰۰ تا ۴۰۰', o400: 'بیشتر از ۴۰۰' };
export const SORT_LABELS: Record<MenuSort, string> = { suggested: 'پیشنهادی', cheap: 'ارزان‌ترین', expensive: 'گران‌ترین', light: 'کم‌کالری‌ترین' };

export const caloriesOf = (p: MenuProduct): number | null => (p.nutrition?.calories && p.nutrition.calories > 0 ? p.nutrition.calories : null);

/** Enough products carry calories for a calorie filter to be honest (at least 3, or a third of the menu). */
export function caloriesUseful(products: MenuProduct[]): boolean {
  const known = products.filter((p) => caloriesOf(p) !== null).length;

  return known >= 3 || (products.length > 0 && known / products.length >= 1 / 3);
}

export function isFiltering(f: MenuFilters): boolean {
  return f.calories !== 'any' || f.moods.length > 0 || f.tags.length > 0 || f.maxPrice !== null || f.availableOnly || f.sort !== 'suggested';
}

function inCalorieRange(kcal: number, f: MenuFilters): boolean {
  switch (f.calories) {
    case 'u200': return kcal <= 200;
    case '200_400': return kcal > 200 && kcal <= 400;
    case 'o400': return kcal > 400;
    case 'max': return f.maxCalories === null || kcal <= f.maxCalories;
    default: return true;
  }
}

/**
 * Products matching the search text and filters, sorted. `unknownCalories` counts the products left
 * out only because they have no calories recorded (shown as an honest note).
 */
export function applyMenuFilters(products: MenuProduct[], query: string, f: MenuFilters, popular: string[] = []): { items: MenuProduct[]; unknownCalories: number } {
  const q = normalizeForSearch(query.trim());
  let unknownCalories = 0;

  const items = products.filter((p) => {
    if (q && !normalizeForSearch(`${p.name} ${p.description ?? ''} ${p.dietary_tags.map((t) => t.label).join(' ')}`).includes(q)) return false;
    if (f.availableOnly && !p.is_available) return false;
    if (f.moods.length && (!p.temperature || !f.moods.includes(p.temperature))) return false;
    if (f.tags.length && !f.tags.every((t) => p.dietary_tags.some((d) => d.key === t))) return false;
    if (f.maxPrice !== null && p.price_from > f.maxPrice) return false;
    if (f.calories !== 'any') {
      const kcal = caloriesOf(p);
      if (kcal === null) { unknownCalories++; return false; }
      if (!inCalorieRange(kcal, f)) return false;
    }

    return true;
  });

  const rank = new Map(popular.map((id, i) => [id, i]));
  const byName = (a: MenuProduct, b: MenuProduct) => a.name.localeCompare(b.name, 'fa');
  const sorted = [...items];
  switch (f.sort) {
    case 'cheap': sorted.sort((a, b) => a.price_from - b.price_from || byName(a, b)); break;
    case 'expensive': sorted.sort((a, b) => b.price_from - a.price_from || byName(a, b)); break;
    case 'light': sorted.sort((a, b) => (caloriesOf(a) ?? Infinity) - (caloriesOf(b) ?? Infinity) || byName(a, b)); break;
    default:
      // Available first, then best sellers, then the café's own order.
      sorted.sort((a, b) => Number(b.is_available) - Number(a.is_available) || (rank.get(a.id) ?? 99) - (rank.get(b.id) ?? 99));
  }

  return { items: sorted, unknownCalories };
}

/** Dietary tags that actually occur on this menu (for the filter sheet). */
export function menuTags(products: MenuProduct[]): { key: string; label: string }[] {
  const seen = new Map<string, string>();
  for (const p of products) for (const t of p.dietary_tags) seen.set(t.key, t.label);

  return [...seen].map(([key, label]) => ({ key, label }));
}

/**
 * Up to `limit` products that go well with the given ones: first what customers actually buy
 * together (insights), then a complementary guess: something from another category, preferring
 * the opposite mood (a cold drink with a warm cake), the café's picks and best sellers.
 */
export function suggestionsFor(menu: Menu, productIds: string[], limit = 3): MenuProduct[] {
  const have = new Set(productIds);
  const byId = new Map(menu.products.map((p) => [p.id, p]));
  const picked: MenuProduct[] = [];
  const add = (p: MenuProduct | undefined) => {
    if (p && p.is_available && !have.has(p.id) && !picked.includes(p) && picked.length < limit) picked.push(p);
  };

  for (const id of productIds) for (const other of menu.insights?.pairs[id] ?? []) add(byId.get(other));
  if (picked.length >= limit || productIds.length === 0) return picked;

  const base = productIds.map((id) => byId.get(id)).filter((p): p is MenuProduct => p !== undefined);
  const categories = new Set(base.flatMap((p) => p.category_ids));
  const moods = new Set(base.map((p) => p.temperature).filter(Boolean));
  const popular = new Set(menu.insights?.popular ?? []);
  const score = (p: MenuProduct) => (p.temperature && !moods.has(p.temperature) ? 2 : 0) + (p.is_featured ? 2 : 0) + (popular.has(p.id) ? 3 : 0);

  menu.products
    .filter((p) => !p.category_ids.some((c) => categories.has(c)))
    .sort((a, b) => score(b) - score(a))
    .forEach(add);

  return picked;
}
