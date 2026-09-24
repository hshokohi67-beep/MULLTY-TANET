# Phase 7b: Stories, Hot/Cold Menu Mood, Category Photos and Storefront Polish (Report)

**Date:** 2026-09-25
**Plan:** `phase-07b-plan.md`
**Status:** Implemented, awaiting approval

## 1. What was built

### Stories
- **Module `Storefront`:** `stories` table (tenant-owned, composite FK to the branch). Actions `ManageStories` (save/reorder/delete). Permission `storefront.manage` (owner and manager).
- **Photos:**
  - every upload is re-encoded by the new `Support\Media\ImageProcessor` (GD): EXIF orientation applied, WebP output, metadata stripped (no GPS leaks), and polyglot files neutralised;
  - the full image has a long side ≤ 1600 px, and the ring thumbnail is a 240 px square;
  - the processor checks the pixel count (≤ 30 MP) and raises the memory limit when needed, because phone photos exceed the default 128 MB.
- **Rules:**
  - 24 h by default (or 3 days, 1 week, 2 weeks, 1 month);
  - at most 30 queued;
  - a link points to one of this tenant's products or categories, or to an **https** URL (other schemes such as `javascript:` or `http:` are rejected);
  - a link to a product that was switched off is dropped automatically on the public side.
- **Counting:** views and clicks are counted once per visitor IP per story per day (cache guard) and throttled.
- **Panel «استوری‌ها» ("stories"):**
  - phone-shaped cards with a status badge (live / scheduled / expired / off) and views, clicks and click rate;
  - drag or arrows to reorder, on/off, and delete with confirmation;
  - an editor drawer with a **live phone-frame preview**, image picker, caption counter, link type (product, category, URL), button text, duration and branch.
- **Dashboard widget `stories`:** live count, and the top stories of the week with views and CTR.
- **Storefront:**
  - an Instagram-style ring row above the menu; unseen stories come first with a brand ring, and seen ones turn grey (localStorage, this device only);
  - a full-screen viewer with RTL progress bars, tap to go back or forward, hold to pause, swipe down or Esc to close, arrow keys, preloading of the next image and screen-reader announcements;
  - the call-to-action opens the **product sheet directly**, scrolls to the category, or opens the URL in a new tab;
  - under reduced motion, a timer replaces the animated bar.

### Hot/cold menu mood
- `categories.temperature` and `products.temperature` (`hot` / `cold` / null). A product without its own value inherits its category's. The public menu returns the effective value.
- **Panel:** «حس دما در منو» ("temperature feel in the menu") on the category forms (with a badge in the list) and an override on the product form.
- **Colours** (validated for contrast; every text pair is AA ≥ 4.5 in both themes):
  - hot is **warm amber/terracotta** (not alarm red);
  - cold is **clear sky blue**;
  - tokens `--mood-hot|cold`, `-ink`, `-soft`, each with its own dark-mode step.
- **Look:**
  - a soft halo behind the photo is visible **at rest** (phones have no hover) and grows on hover, focus or press;
  - a flame/snowflake chip means colour is never the only cue;
  - desktop hover adds steam wisps (hot) or a frost shimmer (cold), switched off under reduced motion.

### Category photos (owner request during the phase)
- `categories.image_path` holds a 320 px square WebP (same processor). Upload and remove happen in the category forms, and the list shows a round thumbnail.
- **Storefront chips** now match the requested shape: a **round picture with the name on a pill sticking out from under it**. The active chip turns brand-coloured. Without a photo, the circle shows a line illustration picked from the name, falling back to the mood (cup for cold, coffee for hot).

### Storefront visual polish
- **Hero:** an optional **cover photo** (new `tenant_branding.cover_path`, uploaded in Settings → brand, re-encoded to ≤ 1920 px WebP). Without one, the brand colour with a quiet dot pattern. On it: the name, the description, and pills for open/closed, today's hours, delivery or pickup, and branch.
- **Product cards:** a larger rounded photo with the mood glow, price pill, «ویژه» ("featured") badge, and an add button that turns green with a quick rotate after adding.
  - Items without a photo get a **line illustration** matching the item (coffee, cake, breakfast, sandwich, salad, cold drink) on a mood-tinted tile instead of a bare letter.
- **Featured row:** tall cards. With a photo, the name and price sit on a soft shade; without one, they sit under the tile.
- **Product sheet:**
  - the photo is shown **whole (never cropped)** on the mood backdrop;
  - the mood and dietary chips, and nutrition as small tiles.
- **Checkout:** a sticky total-and-button bar on phones.
- **Skeletons** matching the real layout for the menu, cart and account.

## 2. Bugs found by the visual pass and fixed
Checked in Chrome (desktop), plus headless Chrome with real 375 px mobile emulation over the DevTools protocol, in light and dark mode.

| Bug | Cause and fix |
|---|---|
| Long dialogs scrolled as a whole and hid their header (affected every long modal/drawer, panel included) | A shared `max-h-full` overrode each variant's cap. The dialog body now has `min-h-0`, and the `<dialog>` is `overflow: clip`, so focusing a control while the sheet slides in can't scroll it |
| Sheet add button overflowed at phone width | Compact stepper; the label shortens to «افزودن» ("add") on phones |
| Bottom bars of the cart (and any `position: fixed` child of a page) were pinned to the page bottom instead of the screen | The page entrance animation kept a `transform` (`fill-mode: both`), which traps fixed children; it is now `backwards` |
| Hero status pill unreadable in dark mode | White pill with theme-light text; the text is now fixed dark |
| Portrait product photos lost the product when cropped to 4:3 | Large views now use `contain` on a backdrop |
| Story images pointed at port 8000 (another local project) | The local `.env` `APP_URL` now points at :8765 |

## 3. Verification

| Check | Result |
|---|---|
| `php artisan test` | **359 passed** (3695 assertions). New `StoriesTest` (7):<br>• WebP re-encode, square thumbnail, 24 h default;<br>• link rules (https only, no `javascript:`, tenant-owned targets, no SVG, minimum size);<br>• live/scheduled/expired and resolved links;<br>• view/click deduplication;<br>• edit replaces files, reorder, delete removes files, cashier gets 403;<br>• mood inheritance and override in the public menu;<br>• category photo 320 px WebP shown in the menu, removal deletes the file. |
| Isolation harness | **120 tests**:<br>• stories list/update/delete/reorder and cover upload/delete for another tenant;<br>• category image endpoints;<br>• B's story invisible and uncountable at A;<br>• B's product rejected as a story link at A. |
| Larastan 0 • Pint | passed |
| Web | typecheck, ESLint, build: clean. `@cafe/ui` tests 5 and `@cafe/locale` tests 8 pass. |
| Visual | Menu at 375 px and desktop, light and dark; product sheet; story viewer; cart; panel stories page and editor. The owner's uploaded photo was checked in the card and sheet. |

## 4. Notes
- **Photo sizes:** the web server accepts uploads up to 10 MB (`serverActions.bodySizeLimit`); the API accepts up to 8 MB and stores a few hundred KB.
- **Demo data:** `StorefrontDemoSeeder` creates three stories with generated artwork (no stock photos), and demo categories carry hot/cold values.
- **Not done:** story video (images only, as decided) and a story scheduler UI beyond duration presets (a start time in the future is supported by the API).
