# Phase 13b: Map pickers, locations, a professional «کافه‌گردی» (Report)

Plan: `phase-13b-plan.md`. Status: done, awaiting approval.

## 1. What was built

### The promised map selection (overdue since the ordering phase)

- **Branch form:** a map picker replaces the raw latitude/longitude inputs. Tap or drag the pin, or use my location; the chosen coordinates are shown and can be cleared. Plus a new «محله / منطقه» ("neighbourhood / district") field.
- **Delivery zones:** each branch now shows a map with the branch pin and every zone's radius as a circle (inactive zones dashed), fitted to the zones. When a branch has no location yet, the warning links to the branch page to set it on the map.
- The storefront `MapPicker` became a shared component (label, pin title, height).

### Backend

- **`branches.district`:** migration, data, request (≤ 60 characters), resource. It is part of the projection and the search text.
- **Projection additions** (`marketplace_stores`): `district`, `offers`, `has_offer`, `dietary_keys` (from active products' tags), `closes_late` (23:00 or past midnight), `free_delivery`.
- **`PublicOffers`:** only automatic, active, not-used-up discounts.
  - Code discounts and discounts limited to particular customers or club tiers are never public.
  - Branch-limited discounts show only on those branches.
  - Labels read like «٪۲۰ تخفیف برای خرید بالای ۱۰۰٬۰۰۰ تومان» ("20% off orders over 100,000 toman"), «(در ساعات مشخص)» ("(at set hours)").
  - Discount changes re-project the café.
- **`home` adds:**
  - `places`: province → city → district, with café counts, only where cafés exist;
  - `collections`: work and study, with offers, late night, budget, breakfast, outdoor; shown only when at least 2 cafés match;
  - `dietary`.
- **`stores` adds:**
  - filters `province`, `district`, `featured`, `offers`, `delivery`, `free_delivery`, `online_payment`, `preorder`, `dine_in`, `late`, `new`, `dietary[]`, `stores[]` (≤ 50 validated slugs, for favourites), `per_page` (map view);
  - sorts `popular`, `price_asc`, `price_desc`;
  - cards now carry district, the first offer, free delivery and coordinates.
- **`GET /public/marketplace/suggest?q=`** (≤ 40 characters, throttled): up to 5 each of cafés, places (city or district), categories and dishes.

### «کافه‌گردی» (web)

- **Location bar «کجا؟»** ("where?"): province → city → area, populated only with places that have cafés, with counts.
  - Choosing any of them opens that area's cafés immediately.
  - The choice is remembered on this device and restored on the next visit.
  - The headline follows the place, e.g. «کافه‌های ونک، تهران» ("cafés in Vanak, Tehran").
- **Search with suggestions:** type-ahead grouped by café, place, category and dish, with full keyboard support (arrows, Enter, Esc) and the current place kept.
- **Quick filters, one tap each and combinable:**
  - «الان باز است» ("open now"), «تخفیف‌دار» ("with offers"), «ویژه» ("featured"), «اقتصادی» ("budget");
  - «ارسال با پیک» ("delivery"), «ارسال رایگان» ("free delivery"), «پرداخت آنلاین» ("online payment"), «پیش‌سفارش» ("pre-order");
  - «تا دیروقت» ("open late"), «تازه‌ها» ("new"), «گزینه‌ی گیاهی» ("vegan options"), «بدون گلوتن» ("gluten-free");
  - «علاقه‌مندی‌ها» ("favourites"), which appears once something is saved.
- **More refinement:** amenities, near me, and six sort options.
- **List / map toggle:** the map shows every result as a pin (featured in a distinct colour) with a mini card built safely from text nodes.
- **Cards:** offer badge, district, «ارسال رایگان» ("free delivery"), and a heart to save to favourites on this device.
- **Home:** featured row, curated collections with «همه» ("all") links, popular, and city tiles with district counts.
- **Profile:** offer chips, dietary chips, district in branch cards, «اشتراک‌گذاری» ("share": Web Share or copy link), favourite.
- **Tokens:** new `--color-on-danger` for text on the offer badge. The café's own brand colour can't lower its contrast.
- **Login:** a platform admin with no café lands directly on `/platform`.

## 2. Verification

- API: 475 tests green.
  - `MarketplaceDiscoveryTest`: only public automatic offers are shown. The code discount, tier-only, expired, `used_count` and `usage_limit` never appear.
  - Places tree; district, late, delivery, free delivery, dietary, favourites and sort filters.
  - Invalid slugs and sorts rejected.
  - Suggestions: place, café, dish; length cap; no tenant ID.
  - Collections (none with one café; «budget» with two).
  - `BranchTest`: district and location saved; validation (length, latitude without longitude).
- Larastan 0, Pint clean. Web typecheck, lint and build green.
- Visual check (headless Chrome):
  - home with collections and offer badges;
  - Tehran results;
  - suggestions dropdown;
  - map view;
  - branch form with the map;
  - delivery zones map (dark);
  - home and results on a 390 px phone (dark) and a café profile (light, 390 px): no horizontal overflow.
- Fixed during the check: the location selects were cramped on phones; they are now a three-column row with short labels.

## 3. Roadmap for «کافه‌گردی» (from the research)

- Ratings and reviews (after an order rating exists).
- Photo galleries and events.
- Trending (from analytics).
- Personalised suggestions.
- Opening-hours exceptions (holidays).
- Sponsored placements (Phase 14).
