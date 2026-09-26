# Phase 17 plan: storefront redesign and café landing pages

## Owner's decisions (2026-09-26)

- **Root address:** when a café publishes its landing page, it becomes the root address (Q3). The «منو و سفارش» button opens `/menu`.
- **Plans:** the landing page and the video hero are in every plan (Q1). There is no entitlement gate.
- **Video:** up to 8 MB is fine (Q2).
- **Variety:** the editor must be more dynamic, so cafés don't all look alike. This led to more look options, a layout variant per section, and a «ترکیب تازه» ("new combination") shuffle.
- **Order:** build Part B (landing page) first, then Part A (menu redesign), which becomes the next step.

## Goal
Every café's storefront gets two things:

1. **A redesigned menu.** It should feel like the café's own place, not a template.
2. **An optional landing page.** Cover, story, highlights, featured dishes, gallery and branches, with one clear «منو و سفارش» ("menu and ordering") button.

The owner's WordPress theme (`theme-front-page/`, "Cafe Luxury Minimal") is the reference for the landing page's mood: dark, editorial, big type, scroll reveals, alternating product showcase, bento highlights, marquee, contact cards. It is rebuilt natively on our data and tokens. It is not ported.

## What we take from the theme, and what we don't

| Take | Leave out, and why |
|---|---|
| Full-bleed hero with a video or photo and a scrim, large light headline | The fake 0→100% preloader: it delays first paint by more than 1 s on every visit |
| Alternating "showcase" rows for featured dishes, with category eyebrow | Tailwind from a CDN and GSAP: they fail under our CSP, are slow in Iran, and a second styling system would split the design |
| Bento "highlight" cards (big number + one line) | The always-running canvases (vapor, steam): they drain CPU and battery on low-end Android |
| Outline-text marquee, oversized faded name in the footer | Blocking right-click and text selection: an accessibility and trust problem, and it doesn't protect photos |
| Contact cards (address + route, hours + "open now", phone + Instagram) | Dark-only colours, English marquee copy, a new random set of products on every load (uncacheable) |
| The "plate assembles on scroll" idea | Hard-coded "Healthy Chef" copy. Instead: a CSS-only layered parallax of the café's own product photos, off under `prefers-reduced-motion` |

## Part A: menu redesign (`/s/[tenant]/menu`), revised 2026-09-26

The owner asked for two things; the rest are proposals (research notes at the end). Items marked *(optional)* wait for the owner's yes.

### A1. Calories on the cards (owner)
- Product cards show «۲۴۰ کالری» next to the name, beside the hot/cold and «ویژه» chips. Products with no calories entered show nothing.
- A new café setting, `storefront.show_calories` (default on, in `TenantSettingsRegistry`), turns the calories off everywhere on the storefront: cards, product page and filters.
- The data already exists (`nutrition.calories` in the product editor; imported from the legacy plugin), so this needs no new data entry.

### A2. Search and filter by calories (owner)
- **Calorie filter:** a «فیلتر» button beside the search box opens a sheet. Calories are chosen by range: «تا ۲۰۰»، «۲۰۰ تا ۴۰۰»، «بیشتر از ۴۰۰»، or a custom maximum.
- **Sorting:** «پیشنهادی»، «ارزان‌ترین»، «گران‌ترین»، «کم‌کالری‌ترین».
- **Products with no calories** are left out while a calorie filter is on, with an honest note (e.g. «۵ محصول کالری ثبت‌شده ندارند»).
- **Combined with search:** active filters show as removable chips under the search box, and search plus filters work together.
- **Visibility:** the calorie parts of the sheet appear only when calories are switched on and enough products have them.

### A3. More filters in the same sheet (proposal)
- Hot/cold (the existing mood).
- Dietary tags: the existing `dietary_tags` (vegetarian, gluten-free…).
- Price range.
- «فقط موجودها» ("available only").
- All client-side on the cached public menu: instant, no new API.

### A4. Menu look (proposal)
- **Presets:** «روشن»، «شب»، «گرم»، «مینیمال», plus «مثل صفحه‌ی معرفی» ("same as the landing page"), which takes the landing template so the café's site and menu feel like one place.
- **Card layout:**
  - photo grid (2 per row on mobile, 3 on tablet, 4 on desktop, photo-led);
  - list (the current one, refined);
  - compact (text only, for long menus or cafés without photos).
- **Tokens:** built on the same tokens as the landing page. Every preset works in light and dark.

### A5. Page structure (proposal)
- **Hero:** a smaller, collapsing hero. On scroll it shrinks into a sticky header with logo, name, open/closed status and search.
- **Category rail:** glass (it floats over content), scroll-spy, with each category's photo or icon.
- **Desktop:** three columns (category list, menu, and a sticky cart panel that replaces the bottom bar on wide screens).
- **Quick add:**
  - after the first tap, the "+" on a card becomes a − ۱ + stepper, so a second cup needs no page;
  - the cart bar bumps softly;
  - sold-out items are dimmed with «ناموجود».

### A6. Product page (proposal)
- Full-bleed photo with a sticky add bar; the price updates with the chosen options.
- Nutrition chips in one row (calories, caffeine, protein), depending on A1.
- Clearer option groups («حداقل ۱ مورد»، «هر تعداد»).
- «کنارش می‌چسبد» ("goes well with it"): 3 items from a complementary category, e.g. a dessert beside a coffee.

### A7. Data-driven badges *(optional)*
- **«پرفروش» ("best seller"):** the café's top 5 products over the last 30 days, from the Analytics aggregates (`product_metrics`, never raw orders).
- **Architecture:** Catalog must not depend on Analytics, so the badge comes through a contract (`Catalog\Contracts\PopularProducts`) that Analytics binds. The public menu gains `is_popular`.
- **Setting:** a café setting can hide the badge.

### A8. Cart suggestions *(optional)*
- In the cart, «معمولاً با این سفارش می‌گیرند» ("usually ordered with this"): up to 3 products bought together with the cart's items, from order co-occurrence over the last 60 days.
- **Data:** computed nightly into a small aggregate, never from raw orders at request time.
- **Fallback:** without enough data, the complementary-category rule from A6.

### Out of scope
- Allergen matrix, per-item videos, AR dishes: not needed for cafés now.

### Research notes (Sept 2026)
- Photos and clear descriptions lift conversion.
- Every extra tap is a conversion leak.
- A 2-second delay raises bounce sharply.
- Surfacing "most popular" items guides decisions.
- Dietary and calorie filters ("under 500 calories") are now expected in web menus, and tags must be applied consistently or customers stop trusting them.

These back A1–A8, the quick-add stepper and the performance budget. Sources: quickbuy.io, menucardstudio.com, supercode.com, usekodo.ai, foodchainmagazine.com.

### Tests
- **API:** the public menu carries the calorie setting (and `is_popular`, if A7 is chosen).
- **Contract (A7):** a café without Analytics data gets no badges.
- **Web:** visual checks at 390 px and 1280 px, light and dark, for each look and card layout.
- **Filters:** combinations of search, filters and sorting, with the empty state.
- **Motion:** reduced motion.

## Part B: café landing page (optional, per café)

### Routing
When the landing page is on:
- `{slug}.cafeyar.ir/` (or `/s/{slug}`) shows the landing page;
- the menu moves to `/menu`.

Table QR codes (`/t/…`), cart, checkout and every existing deep link keep going straight to the menu. When the landing page is off, nothing changes.

### Sections
Each section can be switched on or off and reordered; each is filled from existing data where possible.

1. **Hero**
   - Headline, subline, cover photo (or a short muted video, below).
   - Two buttons: «منو و سفارش» ("menu and ordering") and «مسیریابی» ("directions").
2. **Our story:** title, text (plain paragraphs, no HTML) and a photo.
3. **Highlights:** 2–4 cards, each a big value, a unit and a line (e.g. «۱۰۰٪» ("100%") «دانه‌ی تازه‌برشت» ("freshly roasted beans")).
4. **Featured dishes**
   - The café picks up to 6 products. If none are picked, the landing uses «پیشنهاد ما» ("our picks").
   - Price and availability come live from the catalogue.
   - Each card links to its product page.
5. **Marquee:** up to 4 short phrases (Persian), decorative, `aria-hidden`, paused off-screen and under reduced motion.
6. **Gallery:** up to 12 photos, masonry layout, with a lightbox.
7. **Visit us**
   - Branches with address, map link, today's hours with an open/closed badge, phone, and Instagram.
   - All from branch data; the café types nothing new.
8. **Footer:** oversized faded café name and «ساخته‌شده با کافه‌یار» ("made with Cafeyar").

### Templates
The café picks a template, then edits the text and photos:
- «شب» ("night"): the theme's mood;
- «روشن» ("bright");
- «گرم» ("warm").

### Motion
- Scroll reveals use `IntersectionObserver` and CSS, and fire once.
- The hero photo has a light parallax.
- No libraries.
- Everything is static under `prefers-reduced-motion`.

### Video hero (optional)
- **What is accepted:** MP4 or WebM, 8 MB or less, 20 s or less, with a poster frame taken from an uploaded photo.
- **Storage:** stored as uploaded. There's no transcoding: shared hosting has no ffmpeg. We check the MIME type and magic bytes, and keep it on the media disk under `t/{media_key}/landing/`.
- **Playback:** muted, `playsinline`, `preload="none"`. It plays only on Wi-Fi-class connections, with Save-Data off and motion allowed; otherwise the poster photo shows.
- **Open question:** Q2 below.

### SEO
- The landing page gets real metadata (title, description, OG image).
- It carries a `Restaurant`/`CafeOrCoffeeShop` JSON-LD block built from published data only: name, address, hours, telephone, menu URL.

### Panel screen
- New screen `/dashboard/storefront/landing`, «صفحه‌ی معرفی» ("landing page"), with permission `storefront.manage`.
- Layout: a section list you can toggle and reorder, an editor for each section, template picker, look preset and font.
- A live preview in a phone frame, with a light/dark toggle.
- A «انتشار» ("publish") switch.
- A help topic `storefront-landing`, plus an update to the storefront help topic.

## Database (module `Storefront`)

**`storefront_landings`** (one row per tenant):
- ULID `id`, `tenant_id` (unique);
- `enabled`, `template`, `look`, `font`;
- `hero` json;
- `story` json;
- `highlights` json;
- `featured_product_ids` json;
- `marquee` json;
- `sections` json (order and visibility);
- `published_at`, timestamps.

**`storefront_media`**:
- ULID `id`, `tenant_id`;
- `kind`: `hero_photo`, `hero_video`, `story_photo` or `gallery`;
- `path`, `poster_path`;
- `width`, `height`, `bytes`;
- `caption`, `position`, timestamps.
- Composite FK where child rows exist.

Other changes:
- Tenant setting `storefront.look` goes in `TenantSettingsRegistry` (the menu look also applies when the landing page is off).
- Photos go through `ImageProcessor`. Text is plain and length-capped. Featured ids are validated against the tenant's own products.

## API
**Staff** (`can:storefront.manage`):
- `GET/PUT /storefront/landing`;
- `POST /storefront/landing/media` (photo or video);
- `PATCH/DELETE /storefront/landing/media/{id}`;
- `POST /storefront/landing/media/reorder`.

All of these go into `TenantIsolationTest`.

**Public:** `GET /public/{tenant}/landing` returns the published landing page only: sections, media URLs, featured products with live prices, and branch contact data. It returns 404 when the landing page is off. It is cacheable and bumps `LiveVersion`.

## Security
- Landing text is plain text, rendered as text (never `dangerouslySetInnerHTML`).
- Links are built server-side (menu, product, map, `tel:`, Instagram handle validated).
- Media URLs come only from our media disk.
- Video MIME is sniffed; size and duration are capped. The CSP `media-src` gains the media origin.
- Nothing personal appears on the landing page; it is cached like the menu.

## Plan entitlement
To decide (Q1):
- The landing page is gated `feature:landing` for a higher plan, and the menu look presets are free for everyone;
- or everything is free.

## Tests
- **API:**
  - landing CRUD and validation (text caps, foreign product ids rejected, template enum);
  - media upload (photo processed; video MIME, size and poster rules; wrong magic bytes rejected);
  - public endpoint (404 when off, published only, live prices, no tenant ids);
  - isolation for every endpoint;
  - entitlement gate, if chosen.
- **Web:**
  - typecheck, lint, build;
  - visual checks at 390 px and 1280 px, light and dark, for each template;
  - menu presets;
  - reduced-motion;
  - the subdomain root switching between landing and menu, with QR and cart links still going to the menu.

## Risks
- **Scope:** two big surfaces. Mitigation: ship in two steps inside the phase: A (menu redesign and presets) first, then B (landing page), each visually verified.
- **Video weight on mobile data:** mitigated by poster-first, data-saver and connection checks, and the 8 MB cap. It can be dropped if Q2 says no.
- **Moving the menu to `/menu`:** handled by keeping every deep link (`/t`, `/p`, `/cart`, `/pay`, `/track`) unchanged and only swapping the root.

## Open questions for the owner
1. **Q1:** should the landing page be in every plan, or only higher ones? The menu redesign is for everyone either way.
2. **Q2:** should the cover accept a short video (as in your theme), or photos only at first?
3. **Q3:** when a café turns the landing page on, should its root address open the landing page (menu under `/menu`), or should the landing page live at `/about` and the root stay the menu?
