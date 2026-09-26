# Phase 19 plan: today's specials, discounts on the menu, menu polish

What the owner asked for:
- a «پیشنهاد امروز و تخفیف‌ها» ("today's specials and discounts") slider with a countdown, on the menu and on the landing page;
- discounted prices shown on product cards, and they must be exact;
- desktop category chips that scroll;
- no "AI sparkle" icon anywhere;
- product photos that break out of the card, plus a separate two-column layout;
- cafés can add their own diet and allergy tags;
- help topics updated.

## 1. Menu offers: an exact discounted price on the card
**The problem.** The engine gives one discount per order. So with two advertised offers in one cart, only one would apply, and a crossed-out price on a card would be a promise the cart doesn't keep.

**The fix.** Discounts flagged `show_on_menu` become *menu offers*: price reductions on the items themselves.

**Every line gets its best menu offer:**
- percent: `percentage(unit + modifiers) × qty`;
- fixed: `min(value, unit + modifiers) × qty`.

After that, the existing one-discount rule (a coupon, or the best other automatic discount) runs on the reduced lines. A coupon therefore adds to a sale price instead of cancelling it.

**A menu offer must be unconditional for anyone reading the menu.** `SaveDiscount` validates these rules with Persian messages:
- automatic (no code);
- `applies_to = items`, with at least one product or category;
- no `min_order`, `max_discount`, usage limits, customer, tier or order-type rules;
- branch rules and the time windows (`starts_at`/`ends_at`/schedule) are allowed.

**DB:**
- `discounts.show_on_menu` (bool, default false).
- `products.special_days` (json list of ISO weekdays, nullable) for «پیشنهاد امروز» without a discount.
- **Orders:**
  - `discount_total` = offers + main discount;
  - `discount_snapshot` gets an `offers` list;
  - one `discount_usages` row per offer discount.

**Public menu (`deals`)** is computed after the menu cache, so it is always fresh:
- `offers`: `{id, label, kind, value, product_ids, windows[{from,to}]}`. Windows are UTC instants for the next days in the café's timezone; `to = null` means no end.
- `specials`: `{product_id, windows}`.
- Catalog asks Discounts through a new `Catalog\Contracts\MenuOffers` contract, bound in `DiscountsServiceProvider` (same pattern as `MenuInsights`).

## 2. Web
- **Price logic** lives in `lib/deals.ts` (pure functions):
  - the active offer at time `t`;
  - the offer price with the same rounding as `Money::percentage`;
  - the countdown target.
- **Clock:** `useNow()` corrects the visitor's clock once against a tiny `/s/{tenant}/clock` route handler (no API call).
- **Cards:** struck price, new price and a «٪» chip on every layout, the product sheet and the product page.
- **`DealsSlider`** («پیشنهاد امروز و تخفیف‌ها»):
  - photo-led cards, a badge, a live countdown and quick add;
  - swipe on phones, arrows on desktop;
  - autoplay only without `prefers-reduced-motion`, paused on hover and focus;
  - expired items drop out.
- **Landing:** a new `deals` section (on by default) using the same slider.
- **Cart:** each line shows its offer (struck line total), and totals show «تخفیف منو».
- **Panel:**
  - discount editor: «نمایش روی منو» ("show on the menu") with the rules explained;
  - product editor: «پیشنهاد امروز» weekday picker;
  - setting `storefront.show_deals`.

## 3. Menu polish
- **`ScrollRow`:** horizontal rows (category chips, featured, deals) get arrow buttons on mouse devices and vertical-wheel → horizontal scrolling. This fixes desktop chips that couldn't scroll.
- **List layout:** the photo is larger than the text box and breaks out of the card.
- **New layout `duo`** («دو ستونه»): two columns, with the photo rising out of the top of each card.
- **`Sparkles`** is replaced everywhere with a meaningful icon (crown, award, gift, chef hat, coins…).

## 4. Custom diet and allergy tags
- **Table `menu_tags`** (tenant-owned, ULID, `label`, unique per tenant).
- **Product storage:** products keep keys in `dietary_tags`; custom ones are stored as `c:{id}`.
- **Endpoints** (`catalog.manage`, all in the isolation test):
  - `GET catalog/menu-tags`;
  - `POST catalog/menu-tags`;
  - `DELETE catalog/menu-tags/{id}` (also removes the tag from every product).
- **Scope:** the platform's tags stay the only ones the marketplace filters by. Custom tags work in the café's own menu (filters, search, chips).
- **Product editor:** platform tags, the café's tags, and an inline «برچسب تازه» input.

## 5. Help
Update these topics:
- menu settings (layouts, the slider);
- discounts («نمایش روی منو»);
- product editor (special days, custom tags);
- landing sections.

## Tests
- **Engine:**
  - two offers in one cart both apply;
  - a coupon adds on top;
  - rounding matches the card formula;
  - schedule windows;
  - a branch-restricted offer.
- **Validation:** a disallowed `show_on_menu` combination is rejected.
- **Orders:** placing an order records offer usages; cancelling releases them.
- **Public menu `deals`:** windows across midnight and on weekdays; a hidden product is excluded.
- **Menu tags:** CRUD, removal from products, isolation.

## Risks
- Pricing changes touch checkout: covered by the engine tests plus the existing commerce suite.
- Cached menu pages (60 s): windows cover days, and the client re-checks the time every second, so an offer never looks active after it ends.
