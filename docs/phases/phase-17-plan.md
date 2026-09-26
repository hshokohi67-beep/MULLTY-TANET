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

## Part A: menu redesign (`/s/[tenant]`)

### Hero
- Uses the café's cover photo; photo-less cafés get `StoreHeroArt`.
- The logo overlaps the bottom edge of the cover. Status, hours, fulfilment and branch chips sit on a glass bar.
- On scroll, the hero collapses into a compact sticky header with the logo and the café name.

### Menu page
- **Sticky category rail:** glass, scroll-spy, a horizontal pill row on mobile and a side list on desktop.
- **Desktop layout:** three columns: categories, menu, and a sticky cart panel. The cart panel replaces the bottom bar on screens ≥ 1024 px.
- **Product cards:**
  - larger photo-led cards (2 per row on mobile, 3–4 on desktop);
  - the price and the add button sit on a quiet footer;
  - mood chip (hot/cold);
  - «ویژه» ("special") and «ناموجود» ("sold out") states;
  - a "compact list" view the café can choose instead.
- **«پیشنهاد ما» ("our picks"):** a snap carousel with bigger cards and a subtle entrance animation.
- **Product sheet:** full-bleed photo, sticky add bar, grouped options with clearer min/max hints.
- **Footer:** hours, map link, phone and socials as cards (the theme's contact cards, in our tokens).

### Store look presets (`storefront.look`)
- A preset sets typography scale, radius, surface style and card layout. The brand colour still flows through `brandCss()`, and every preset works in both themes.
- Presets:
  - «روشن» ("bright", the current look, refined);
  - «شب» ("night", dark editorial, like the theme);
  - «گرم» ("warm": paper tones, soft radii);
  - «مینیمال» ("minimal": list layout, no art).
- Headline font choice from bundled open-licence fonts:
  - Vazirmatn (current);
  - Samim (OFL).
  - «Iranian Sans» from the theme is **not** bundled: its licence is commercial or unclear.

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
