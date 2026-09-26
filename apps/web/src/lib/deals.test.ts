import { test } from 'node:test';
import assert from 'node:assert/strict';
import { dealItems, dealsAt, itemsTotal, nextChange, offerAmount, offerPrice, percentOff, windowAt } from './deals.ts';
import type { DealWindow, Menu, MenuOffer, MenuProduct } from './storefront-types.ts';

const product = (id: string, price: number): MenuProduct => ({
  id, slug: id, name: id, description: null, is_featured: false, temperature: null, is_available: true, category_ids: [],
  price_from: price, variants: [{ id: `${id}-v`, name: null, price }], images: [], nutrition: null, dietary_tags: [], modifier_groups: [],
});

const offer = (id: string, kind: 'percent' | 'fixed', value: number, productIds: string[], windows: DealWindow[] = [{ from: null, to: null }], priority = 0): MenuOffer => ({
  id, label: id, kind, value, priority, product_ids: productIds, windows,
});

const T = Date.parse('2026-09-27T12:00:00Z');

test('prices use the API formula (percent rounded half up, fixed capped at the price)', () => {
  const p15 = offer('a', 'percent', 1500, []);
  assert.equal(offerAmount(650_000, p15), 97_500);
  assert.equal(offerPrice(650_000, p15), 552_500);
  assert.equal(offerAmount(333, offer('b', 'percent', 1250, [])), 42); // 41.625 → 42
  assert.equal(offerPrice(80_000, offer('c', 'fixed', 100_000, [])), 0);
  assert.equal(percentOff(200_000, offer('d', 'fixed', 50_000, [])), 25);
});

test('windows: half-open, open-ended, and the next change', () => {
  const w: DealWindow[] = [{ from: '2026-09-27T10:00:00Z', to: '2026-09-27T12:00:00Z' }, { from: '2026-09-27T18:00:00Z', to: null }];
  assert.equal(windowAt(w, T), null); // 12:00 is the end, not inside
  assert.deepEqual(windowAt(w, Date.parse('2026-09-27T11:59:59Z')), w[0]);
  assert.deepEqual(windowAt(w, Date.parse('2030-01-01T00:00:00Z')), w[1]);

  const menu: Menu = { branch: { id: 'b', name: 'b', slug: 'b' }, categories: [], products: [product('x', 100_000)], insights: { popular: [], pairs: {} }, generated_at: '',
    deals: { offers: [offer('o', 'percent', 1000, ['x'], w)], specials: [] } };
  assert.equal(nextChange(menu, T), Date.parse('2026-09-27T18:00:00Z'));
});

test('each product gets its best offer on now; specials and offers combine; the slider leads with discounts', () => {
  const menu: Menu = {
    branch: { id: 'b', name: 'b', slug: 'b' }, categories: [], insights: { popular: [], pairs: {} }, generated_at: '',
    products: [product('latte', 850_000), product('cake', 400_000), product('soup', 300_000), { ...product('gone', 100_000), is_available: false }],
    deals: {
      offers: [
        offer('ten', 'percent', 1000, ['latte', 'cake']),
        offer('fixed', 'fixed', 100_000, ['latte']),                                                     // 100,000 > 85,000 on the latte
        offer('later', 'percent', 5000, ['cake'], [{ from: '2026-09-28T00:00:00Z', to: null }]),          // not on yet
      ],
      specials: [{ product_id: 'soup', windows: [{ from: null, to: '2026-09-27T20:30:00Z' }] }, { product_id: 'gone', windows: [{ from: null, to: null }] }],
    },
  };
  const deals = dealsAt(menu, T);
  assert.equal(deals.get('latte')?.offer?.id, 'fixed');
  assert.equal(deals.get('cake')?.offer?.id, 'ten');
  assert.equal(deals.get('soup')?.special, true);
  assert.equal(deals.get('soup')?.specialEndsAt, Date.parse('2026-09-27T20:30:00Z'));
  assert.deepEqual(dealItems(menu, deals).map((i) => i.product.id), ['latte', 'cake', 'soup']); // sold out stays out
});

test('the cart shows items after their offers', () => {
  assert.equal(itemsTotal({ subtotal: 2_400_000, offers: [{ amount: 195_000 }, { amount: 100_000 }] }), 2_105_000);
  assert.equal(itemsTotal({ subtotal: 50_000 }), 50_000);
});
