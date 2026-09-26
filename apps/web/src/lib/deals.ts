import type { DealWindow, Menu, MenuOffer, MenuProduct } from './storefront-types';

/**
 * Menu offers and today's specials, as pure functions of the public menu and a moment `t`
 * (ms since epoch). The API sends time windows (UTC instants), so nothing here needs the café's
 * timezone. Prices use the API's formula (`MenuOffers::unitPrice`): the card shows exactly what
 * the cart will charge.
 */

const at = (iso: string | null, open: number) => (iso === null ? open : Date.parse(iso));

/** The window that contains `t`, if any. */
export function windowAt(windows: DealWindow[], t: number): DealWindow | null {
  return windows.find((w) => at(w.from, -Infinity) <= t && t < at(w.to, Infinity)) ?? null;
}

/** Rial taken off one item: percent is rounded half up like `Money::percentage`. */
export function offerAmount(price: number, offer: MenuOffer): number {
  const off = offer.kind === 'percent' ? Math.floor((price * offer.value + 5000) / 10000) : offer.value;

  return Math.max(0, Math.min(price, off));
}

export const offerPrice = (price: number, offer: MenuOffer) => price - offerAmount(price, offer);

/** "۲۰٪" for percent offers; fixed ones are rounded to a whole percent of this price. */
export function percentOff(price: number, offer: MenuOffer): number {
  return offer.kind === 'percent' ? Math.round(offer.value / 100) : price > 0 ? Math.round((offerAmount(price, offer) / price) * 100) : 0;
}

export interface ProductDeal {
  /** The best offer on now for this product (by its lowest price), and when it ends (null = no end). */
  offer: MenuOffer | null;
  offerEndsAt: number | null;
  /** «پیشنهاد امروز», and when today's run ends (null = every day). */
  special: boolean;
  specialEndsAt: number | null;
}

/** What is on now, per product id. Products with nothing on are left out. */
export function dealsAt(menu: Menu, t: number): Map<string, ProductDeal> {
  const out = new Map<string, ProductDeal>();
  const deals = menu.deals;
  if (!deals) return out;
  const prices = new Map(menu.products.map((p) => [p.id, p.price_from]));
  const entry = (id: string) => {
    let d = out.get(id);
    if (!d) { d = { offer: null, offerEndsAt: null, special: false, specialEndsAt: null }; out.set(id, d); }

    return d;
  };

  for (const offer of deals.offers) {
    const w = windowAt(offer.windows, t);
    if (!w) continue;
    for (const id of offer.product_ids) {
      const price = prices.get(id);
      if (price === undefined) continue;
      const d = entry(id);
      const better = !d.offer
        || offerAmount(price, offer) > offerAmount(price, d.offer)
        || (offerAmount(price, offer) === offerAmount(price, d.offer) && offer.priority > d.offer.priority);
      if (better) { d.offer = offer; d.offerEndsAt = w.to === null ? null : Date.parse(w.to); }
    }
  }
  for (const s of deals.specials) {
    const w = windowAt(s.windows, t);
    if (!w || !prices.has(s.product_id)) continue;
    const d = entry(s.product_id);
    d.special = true;
    d.specialEndsAt = w.to === null ? null : Date.parse(w.to);
  }

  return out;
}

/** The next moment something starts or ends (so the page can re-render then), or null. */
export function nextChange(menu: Menu, t: number): number | null {
  let next: number | null = null;
  const all = [...(menu.deals?.offers ?? []).flatMap((o) => o.windows), ...(menu.deals?.specials ?? []).flatMap((s) => s.windows)];
  for (const w of all) {
    for (const edge of [w.from, w.to]) {
      const e = edge === null ? null : Date.parse(edge);
      if (e !== null && e > t && (next === null || e < next)) next = e;
    }
  }

  return next;
}

/** The slider's items: discounts first (biggest first, specials among them ahead), then today's specials; sold-out items stay out. */
export function dealItems(menu: Menu, deals: Map<string, ProductDeal>, limit = 12): { product: MenuProduct; deal: ProductDeal }[] {
  const score = (p: MenuProduct, d: ProductDeal) => (d.offer ? 1000 + percentOff(p.price_from, d.offer) : 0) + (d.special ? 200 : 0);

  return menu.products
    .filter((p) => p.is_available && deals.has(p.id))
    .map((p) => ({ product: p, deal: deals.get(p.id) as ProductDeal }))
    .sort((a, b) => score(b.product, b.deal) - score(a.product, a.deal))
    .slice(0, limit);
}

/** When the card's countdown runs to: the offer's end, else today's special's end. */
export function dealEndsAt(d: ProductDeal): number | null {
  return d.offer ? d.offerEndsAt : d.specialEndsAt;
}

/** What the cart's items cost after their menu offers (before the order's one discount and delivery). */
export function itemsTotal(quote: { subtotal: number; offers?: { amount: number }[] }): number {
  return quote.subtotal - (quote.offers ?? []).reduce((s, o) => s + o.amount, 0);
}
