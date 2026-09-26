# Phase 19 report: today's specials, discounts on the menu, menu polish

## What was built

### Exact discounted prices on the menu (menu offers)
- **Opt-in.** A discount marked «نمایش روی منو» ("show on the menu") is a *menu offer*.
- **Priced per item.** It is taken off each item, and every cart line gets its best offer. This happens before the order's one discount (coupon or best automatic) and on top of it.
  - Two advertised offers in one cart therefore both hold.
  - A coupon applies to what is left after the offers.
- **Same formula as the card.** Per unit, including add-ons: `Money::percentage` rounding for percent, or `min(value, unit)` for fixed. The web's `lib/deals.ts` uses the same formula, so the card and the cart always agree.
- **Unconditional by design.** `MenuOffers::problems` rejects, with Persian messages:
  - a code;
  - an order-level discount, or one without products/categories;
  - a minimum order or cap;
  - usage limits;
  - customer, tier or order-type rules.

  Branch rules and time windows are allowed.
- **Orders:**
  - `discount_total` = offers + main discount;
  - `discount_snapshot.offers` records the offers;
  - one `discount_usages` row per offer discount (released on cancel, as before);
  - the order's discount name falls back to the offer names.
- **Public menu `deals`**, built after the menu cache so it is always current:
  - **offers:** product ids, kind/value, priority, and **time windows** as UTC instants. The windows mirror the engine exactly: the weekday of the moment, daily hours wrapping past midnight, and starts/ends.
  - **today's specials:** whole days in the café's timezone.

  Catalog reads offers through a new `MenuOfferFeed` contract bound by Commerce. Catalog and Discounts stay siblings.

### «پیشنهاد امروز» (today's special)
- `products.special_days` holds ISO weekdays.
- In the product editor, «پیشنهاد روز» ("special of the day") is a weekday picker.
- On those days the product gets a «پیشنهاد امروز» ("today's special") chip and goes into the slider. The price is unchanged.
- A product can also be in a menu offer; then its card shows both the chip and the struck price.

### Storefront
- **`DealsSlider`** («پیشنهاد امروز و تخفیف‌ها», "today's specials and discounts"):
  - photo cards with a big «۲۰٪ تخفیف» badge, the offer name, the struck and new price, and a live countdown («۲ روز» or `۰۵:۱۲:۴۰`);
  - quick add;
  - autoplay only when the visitor allows motion, paused on hover or focus;
  - items drop out when they end.
- **Clock:** the countdown uses the web server's clock (`/s/{tenant}/clock`; no API call), so a wrong phone clock doesn't lie.
- **Where discounted prices appear:**
  - every card layout;
  - «پیشنهاد ما» ("our picks");
  - «کنارش می‌چسبد» ("goes well with it") suggestions;
  - the product sheet and product page (banner with countdown);
  - size prices and the add button total (add-ons included);
  - the cart panel, cart bar and checkout (per line and «جشنواره‌ی …» total rows).
- **Landing page:** a new «پیشنهاد امروز و تخفیف‌ها» section, slider or grid.
  - It is visible by default and hidden while nothing is on.
  - Pages saved earlier get the section automatically (`LandingSchema::mergeContent` / `mergeSections`).
- **Desktop category chips now scroll** (`ScrollRow`): arrows on mouse devices, and the vertical wheel scrolls sideways. It is also used for «پیشنهاد ما» and the slider.
- **Layouts:**
  - «فهرستی» (list): the photo is larger than the text box and breaks out of the card's top.
  - New «دو ستونه» (two-column, `duo`): the photo rises out of each card's top.
- **Setting** `storefront.show_deals` («اسلایدر پیشنهاد امروز و تخفیف‌ها», the slider switch). Card prices show offers either way.

### Panel
- **Discount editor:**
  - «نمایش روی منو» ("show on the menu"), with the rules explained; it hides the fields an offer can't have;
  - «روی چه چیزی؟» ("applies to what?"): the whole order, or products/categories via category chips and a searchable product list (item discounts previously needed the API);
  - «تا تاریخ مشخص» ("until a set date"): a Jalali date plus `ClockSelect` for the end time, in the café's timezone.
- **Product editor:**
  - «پیشنهاد روز» days;
  - diet/allergy tags as the platform list plus the café's own. «برچسب تازه» ("new tag") is added inline and checked at once. Deleting a tag asks inline, then removes it from every product.

### Café diet/allergy tags
- A new `menu_tags` table (tenant-owned); products store `c:{id}`.
- `GET/POST/DELETE catalog/menu-tags` require `catalog.manage` and are in the isolation test.
- Custom tags work in the café's menu: chips, filters and search. The marketplace keeps filtering by platform tags only.

### Other
- The `Sparkles` ("AI") icon is gone everywhere. It was replaced by crown, award, gift, graduation cap, star, gem, thumbs-up, coins, shopping bag, hand-platter and landmark icons, chosen by meaning.
- Help topics updated:
  - discounts (a new «نمایش روی منو» section with an example, plus an FAQ);
  - menu («پیشنهاد روز», custom tags);
  - landing;
  - storefront (slider, layouts, desktop scrolling);
  - settings.

## Tests and checks
- **API: 580 tests passed** (1 skipped as before). New and updated tests:
  - `MenuOffersTest`:
    - two offers plus a coupon in one cart, with exact amounts;
    - checkout usages and the snapshot;
    - an offer next to an ordinary automatic discount;
    - validation;
    - windows across midnight matching the engine;
    - specials as whole days, and keep/clear.
  - `MenuTagTest`: CRUD, labels in the public menu, removal from products, permissions.
  - `LandingTest`: an old page without the deals section.
  - Isolation entries for `menu-tags`.
- **Web:** a new `npm test` (`node --test`) for `lib/deals.ts`, covering price rounding against the API formula, windows, best offer per product, slider order and cart totals. All pass.
- **Static checks:** Larastan level 6 reports 0 errors; Pint, web typecheck, lint and build pass.
- **Visual checks** at 375 px and 1280 px, light and dark:
  - menu slider, list and two-column layouts, desktop chips with arrows;
  - discount editor, product editor tags and special days;
  - landing section.

## Notes
- The tier colour still isn't shown on menu cards. Tier and members-only discounts are personal, so they appear only in the cart. The menu is one cached page for everyone.
- Offer windows are sent for the next ~3 days and the menu is cached for 60 s. The client re-evaluates at each window edge, so an offer never looks active after it ends.
