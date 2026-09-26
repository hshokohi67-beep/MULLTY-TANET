# Phase 17 report, part B: café landing pages

Part A (menu redesign) is next. Its plan is in `phase-17-plan.md`.

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
