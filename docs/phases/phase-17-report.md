# Phase 17 report: café landing pages (B) and the menu redesign (A)

Part B shipped first (db3f0f1); part A is below it.

## What shipped

### A landing page per café, as its home
- When published, `{slug}.cafeyar.ir/` (and `/s/{slug}`) shows the landing page. The menu moves to `/menu`.
- When unpublished, the root is the menu, exactly as before.
- These keep going straight to the menu:
  - table QR codes (`/t/…` now redirects to `/menu?branch=…`);
  - the cart's "continue shopping" link;
  - the account page's «دیدن منو» ("see the menu");
  - product links.
- Route restructure:
  - `s/[tenant]/layout` is the shared shell (brand colour, visitor state, cart bar);
  - `(shop)/layout` adds the shop header and footer (`ShopChrome`);
  - the menu body is `MenuHome`, used by `/menu` and by the root when there is no landing page.

### Look options, so cafés don't look alike
| Option | Values |
|---|---|
| Template (حال‌وهوا) | night (always dark, brand colour re-derived for dark), bright, warm (paper tones in both themes), bold (brand-coloured sections) |
| Hero (چیدمان سردر) | cover (full-bleed photo/video), center, split (text + framed photo), poster (giant faint name + arch photo) |
| Headline font | Vazirmatn, Samim (bundled, open licences, see `app/fonts/Samim-LICENSE.txt`) |
| Headline weight | light (editorial), bold |
| Texture | glow, none, grain, dots, food drawings |
| Corners | soft, sharp, round |
| Motion | subtle, none, lively (scroll reveals; lively adds a scroll-driven drift) |

Every section also has its own layout:
- story: photo end, photo start, text only;
- highlights: bento, row, numbers;
- featured: showcase, grid, carousel;
- marquee: faint, coloured;
- gallery: masonry, strip;
- visit: cards, compact.

«یک ترکیب تازه پیشنهاد بده» ("suggest a new combination") shuffles the look and the layouts without touching the words.

### Sections
Hero, story, highlights (≤ 4), featured dishes, marquee, gallery, visit, footer.
- **Featured dishes:** up to 6. They show live prices and link to the product page. If the café picks none, the page uses «پیشنهاد ما» ("our picks").
- **Marquee:** up to 4 phrases.
- **Gallery:** up to 12 photos, with a larger photo viewer.
- **Visit:** every branch, with today's hours, an open/closed badge, the weekly hours, directions and a phone link.
- **Empty sections** are not rendered.
- **SEO:** the landing page carries `CafeOrCoffeeShop` JSON-LD and OG metadata.

### Media
- **Photos:** re-encoded to WebP by `ImageProcessor`, with EXIF stripped.
- **Hero video:**
  - MP4 or QuickTime, up to 8 MB;
  - checked by its bytes (`VideoSanitizer`);
  - `udta`/`meta`/`uuid` boxes are renamed to `free`, so the GPS, device and owner metadata a phone records never goes public (offsets unchanged, no transcoding);
  - played only when the visitor can afford it (no Save-Data, 4G-class connection, motion allowed); otherwise the hero photo shows.
- **CSP** gains `media-src`. The server action body limit is now 12 MB.

### Panel: «صفحه‌ی معرفی» ("landing page") (`storefront.manage`)
- **Three tabs:**
  - look (template swatches drawn with real tokens, hero sketches, option chips);
  - text and sections (hero words; reorder, show/hide, layout and fields for each section; product picker with search);
  - photos and video (uploads apply at once; captions; reorder; two-step delete).
- **Live preview:**
  - uses the storefront's own components, as a phone or a scaled desktop, with a dark toggle;
  - uses container queries, so the preview matches a real phone even inside the panel.
- **Publishing and saving:**
  - a publish switch, an unsaved-changes badge and a leave-page warning;
  - after saving, the live site updates within a minute (cached public data).
- **Wiring:** sidebar entry, command-palette keywords, and help topic `landing`. The storefront help topic now describes the subdomain address and `/menu`.

### Fixes found along the way
- The table QR cookie was still scoped to `/s/{slug}`, which is wrong on a café subdomain. It now uses the same Host-based scope as the cart (`cookiePathFor`).
- `api()` returned `null` on a 200 response that wasn't JSON (a proxy error page), which crashed pages with an unclear error. It now throws `api_bad_response` (502).

## API
- `GET/PUT /storefront/landing`
- `POST /storefront/landing/media`
- `PUT /storefront/landing/media/order`
- `PATCH/DELETE /storefront/landing/media/{landingMedia}`
- `GET /public/landing`: 404 until published; no tenant id; featured ids filtered to active products.

Tables: `storefront_landings` (one per tenant: design, content, sections as JSON) and `storefront_media`.

## Checks
- **API:**
  - `LandingTest`: 6 tests covering defaults, publish/unpublish, validation of every option, own-products only, WebP re-encoding and replacement, the gallery cap, order and captions, a real MP4 losing its GPS box, a fake or truncated MP4 refused, and cashiers refused;
  - all new endpoints added to `TenantIsolationTest`;
  - full suite green; Larastan level 6 and Pint clean.
- **Web:** typecheck, lint and build clean.
- **Visual (390 px and 1280 px, light and dark):**
  - night + cover;
  - warm + split + food texture;
  - bold + poster in the editor's desktop preview;
  - the editor's three tabs;
  - a real gallery upload through the editor;
  - saving from the editor, then the live page;
  - `/menu`;
  - QR redirect to `/menu` on the subdomain.
- **Real video:** the owner uploaded a real MP4 (1.7 MB) during testing. It passed the sanitizer and plays in the split hero.

## Notes
- **Hosting:** PHP needs `upload_max_filesize ≥ 12M` and `post_max_size ≥ 16M`. Both deploy guides (English and Persian) now say so.
- **Video codec:** HEVC videos from recent iPhones may not play on some Android browsers. The hero photo stays underneath, so nothing breaks. The upload hint asks for H.264 MP4.

---

# Part A: menu redesign

All eight items of the plan were approved and shipped.

### A1. Calories on the cards
- Every card shows «۲۴۰ کالری» next to the hot/cold, «ویژه» and «پرفروش» chips, when the product has calories.
- A new setting, `storefront.show_calories` (default on), hides calories everywhere: cards, product page and filters.

### A2. Calorie search and filter
- **The «فیلتر» sheet:** calorie ranges (≤ 200, 200–400, > 400) or a custom maximum, plus a «کم‌کالری‌ترین» sort.
- **Honesty about gaps:** products without calories are left out while a calorie filter is on, and the page says how many.
- **Combined with search:** active filters show as removable chips under the search box, and the search text works together with them.
- **When it appears:** the calorie part shows only when calories are on and enough products have them (`caloriesUseful`).

### A3. Other filters
Hot/cold, the dietary tags that occur on this menu, price steps, and «فقط موجودها» ("available only").
- Sorting: suggested (available first, then best sellers, then the café's order), cheapest, most expensive, lightest.
- Everything is client-side over the cached menu (`lib/menu-logic.ts`, pure functions).

### A4. Menu look and card layout
- **Setting `storefront.menu_look`:** bright, warm, night, minimal, or «مثل صفحه‌ی معرفی» (follows the landing page's template; bold maps to bright).
  - Applied by `ShopChrome` through `.store-look[data-template]`, which reuses the landing-page tokens.
  - The night look re-derives the brand colour for dark (`lib/theme-css.ts`).
- **Setting `storefront.menu_layout`:** list (photo beside text), grid (photo-led tiles, 2 or 3 columns), compact (text rows).
- **Panel:** a new «منوی آنلاین» ("online menu") card on the settings page holds both, plus the three switches.

### A5. Page structure
- **Hero:** smaller. When it scrolls away, the sticky bar shows the logo, name and open/closed status.
- **Wide screens:** three columns (category list with scroll-spy, menu, cart panel). The floating cart bar hides there via `:has([data-cart-panel])`.
- **Quick add:** after the first «+», the button becomes a − ۱ + stepper; the last «−» removes the line.
- **Sold-out:** dimmed with «ناموجود».

### A6. Product page and sheet
- They already had the sticky add bar with a live price and nutrition tiles. They now respect the calorie setting.
- They add «کنارش می‌چسبد» ("goes well with it"). Tapping opens that product in the sheet, or goes to its page from a page; «+» adds it at once.

### A7. «پرفروش» ("best seller")
- **Contract:** Catalog's new `MenuInsights` contract (`NoMenuInsights` by default) is bound by Analytics (`ProductInsights`).
- **Rule:** top 5 by quantity over 30 days from `product_metrics`, with at least 5 sold.
- **Output:** the public menu carries `insights.popular`, filtered to products on the menu.
- **Switch:** `storefront.show_popular`.

### A8. «معمولاً با این سفارش می‌گیرند» ("usually ordered with this")
- **New aggregate `product_pair_metrics`:** built by `RollupDay`, one row per pair per day per branch; a huge order counts only its first 12 products.
- **Output:** `ProductInsights::pairs()` sums 60 days (at least 2 shared orders), top 5 per product, cached for an hour. The public menu carries `insights.pairs` (top 3 per product).
- **Where it shows:**
  - the product sheet and product page;
  - the desktop cart panel;
  - the cart page (server action `cartSuggestions`: branch-checked, public cached menu only).
- **Fallback:** without data, the rule picks another category, the opposite mood, the café's picks and best sellers.
- **Switch:** `storefront.suggestions`.

### Fixed along the way
- **`CartBar`:** it compared the path to `/s/{tenant}`, so on a café subdomain (path `/` or `/menu`) the bar never appeared. It now strips the prefix and shows on the home, `/menu` and product pages.
- **Product page JSON-LD:** the `<` escape had a single backslash, so `'<'` evaluated to `<` and did nothing. A café could have injected `</script>` through a product name. It is now properly escaped, and the breadcrumb points at `/menu`.
- **`RollupDay`:** the first draft of the pair loop reused `$orders` and broke the next branch. It was caught on the dev data (a two-branch café) before commit. A regression test now covers two branches.
- **Leftover code:** the Kavenegar key handling left in `updateSettings` was removed.

### Checks (part A)
- **API:**
  - `MenuInsightsTest`, 3 tests: the rollup builds pairs across two branches; best sellers respect the window and the minimum; pairs are symmetric; switched-off products and the settings are respected; menu settings are published and validated;
  - full suite: 548 passed, 1 skipped;
  - Larastan level 6: 0 errors; Pint clean.
- **Web:** typecheck, lint and build clean.
- **Visual (390 px and 1280 px, light and dark):**
  - bright list with calories and «پرفروش», plus the compact sticky bar;
  - the filter sheet and the filtered result with chips and the honest count;
  - desktop three columns with the cart panel and suggestions;
  - warm grid in both themes;
  - night compact on desktop;
  - the product sheet with «کنارش می‌چسبد»;
  - cart suggestions;
  - the settings card.
- **Dev data:** 60 days of the demo café were rolled up (1,904 pair rows) and feed the badges and suggestions.
