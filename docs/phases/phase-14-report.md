# Phase 14: Advertising + a visual redesign of «کافه‌گردی» (Report)

Plan: `phase-14-plan.md`. Status: done, awaiting approval.

## 1. What was built

### Advertising (module `app/Modules/Advertising`)

**Placements.** `ad_placements` is platform-level, with prices and capacity editable in `/platform/ads`:

| Placement | Where | Seed price | Capacity |
|---|---|---|---|
| `home_banner` | Banner carousel on the «کافه‌گردی» home, and above results in the targeted city | 180,000 toman/day | 6 |
| `search_top` | Up to 2 «تبلیغ» ("ad") cards at the top of search and category results | 90,000 toman/day | 12 |

**Campaigns.** `ad_campaigns` is tenant-owned. A campaign has one placement, a start day plus 1–30 days, target cities and one creative:

- headline ≤ 40, text ≤ 90;
- a call to action from a fixed list;
- an image re-encoded by `ImageProcessor` into a 1600 px and an 800 px WebP.

Links go only to the café's own profile or menu. There are no free URLs.

**Lifecycle.** `draft` → `pending` (review) → `approved` → `paid`. Side paths:

- `rejected` (with a note): edit and resend.
- `cancelled`: only before payment.
- `suspended`: the platform pauses it with a reason.

Rules:

- Editing an approved campaign sends it back to review and re-prices it.
- A paid campaign is frozen.
- Capacity is checked at submit, at approval and when the payment lands.

**Payment.** It goes through the Phase 12 billing flow, generalised:

- `billing_invoices` gains the kind `ad` plus `subject_id`. `plan_id`, `cycle` and `mode` are now nullable.
- Billing got the contract `InvoiceFulfiller`, and Advertising registers `FulfilAdInvoice` against it.
- Only subscription invoices void other open subscription invoices. An ad invoice and a plan invoice never cancel each other.
- Ads have their own verify route (`ads.manage`; billing permission not needed). It stays usable in read-only mode, like billing, so a payment already made can always be confirmed.
- If the campaign changed state, the amount doesn't match, or the slot filled meanwhile, the payment is kept. The campaign returns to the review queue with `payment_issue` set, and approving it switches it straight on.

**Serving.** Marketplace defines `SponsoredContent`, with a null default, and Advertising binds `AdServing`. It reads the public `ad_slots` projection, which `ProjectCampaign` writes on every campaign save.

- An ad is shown only while its café is in the marketplace projection.
- Sponsored results come only from cafés that already match the search.
- Sponsored cafés are taken out of the organic list. They never appear on page 2 or in favourites.
- A city-targeted ad is shown only for that city. With no city chosen, only untargeted ads appear.
- Rotation: the order changes every 5 minutes.
- Labels: «تبلیغ» (paid) is distinct from «ویژه» (featured add-on).

**Metrics.**

- `POST /public/ads/events` accepts an impression or a click with an HMAC token (`ref.placement.expiry.sig`, valid 2 hours).
- It counts one impression per visitor per hour and one click per visitor per day. The visitor key is an IP + user-agent hash, kept only in the cache.
- The endpoint is throttled and always answers 204.
- Counters go to `ad_daily_stats` (café's local day). No raw events are stored.
- The web counts an impression only when the ad is at least half visible for one second.

**Café panel `/dashboard/ads`** (permission `ads.manage`: owner and manager):

- KPIs with sparklines, and a daily impressions chart;
- campaigns with a status badge, review notes, stats and actions (send, pay, edit, cancel);
- a builder with a live preview of the real banner or sponsored card, placement cards, Jalali start day, day chips, cities, image upload, and a quote (VAT and remaining slots);
- dashboard widget `ads` («تبلیغات در کافه‌گردی»).

**Platform `/platform/ads`:**

- status tabs with counts (review queue first, payment issues);
- creative preview;
- approve, reject or suspend with a reason, and resume;
- placement price, capacity and on/off.

### «کافه‌گردی» visual redesign

- **Generated cover art** (`components/explore/Art.tsx`) for every café without a photo: its brand colour gradient, its category icons as a pattern, and a monogram. Monogram logos too. Pages full of photo-less cafés now look varied.
  - The demo cafés got their own brand colours.
  - Contrast stays safe through `brandCss`.
- **Hero:**
  - layered glow and a faint café-icon pattern;
  - a headline with an underlined accent word;
  - live counters («۸ کافه همین حالا باز است», "8 cafés open right now");
  - a floating collage of real cafés on desktop.
- **Categories:** large tinted round tiles.
- **Banner carousel:**
  - swipe on phones, arrows and dots on desktop;
  - pauses on hover, focus, off-screen, hidden tab and reduced motion;
  - a house slide for café owners when there are few banners.
- **Rows that differ:**
  - featured → spotlight cards with text over the image;
  - collections → snap rails with desktop arrows;
  - popular → «برترین‌ها» ("top picks"), a ranked list with large numerals;
  - cities → art tiles with a landmark icon;
  - owner call-to-action band;
  - richer footer.
- **Result cards:**
  - clearer hierarchy, district/category chips, a services row (delivery / free, online payment, pre-order);
  - offer ribbon, «تبلیغ» / «ویژه» labels;
  - logo overlapping the cover.
- **Collections:** a row that adds no new café is skipped. With a city chosen, the platform-wide rows are hidden, so other cities don't mix into a city's results.
- **Profile:**
  - generated cover and monogram;
  - menu highlights use the storefront illustration fallback;
  - «کافه‌های مشابه» ("similar cafés"): same category, same city first;
  - a sticky «منو و سفارش» ("menu and order") bar on phones.
- **New tokens:** `--color-on-media`, `--color-on-media-muted`, `--color-scrim` for text and scrims over images.
- **Fixed while doing this:**
  - `--color-on-danger` was missing from the forced-dark theme;
  - the danger `Button` used hard-coded white.

## 2. Verification

- **API: 494 tests green**, including `AdvertisingTest` (8 tests):
  - banner lifecycle, dates and suspension;
  - a café leaving the marketplace takes its ads with it;
  - edit, review and cancel rules; drafts stay private from the platform;
  - listing and capacity checks;
  - sponsored relevance, labels, targeting, no duplicates, not on page 2 or in favourites;
  - event tokens (forged, expired, dedupe per hour and per day);
  - ad and subscription invoices independent, and the subscription untouched by an ad payment;
  - a payment that lost its slot is kept and flagged;
  - permissions and platform-only routes.
- **Isolation harness:** covers all 11 new tenant endpoints.
- **Static checks:** Larastan 0 errors, Pint clean.
- **Web and packages:** typecheck, lint and build green; ui 6 and locale 8 tests green.
- **Visual checks (headless Chrome):**
  - explore home (1280 light, 390 dark);
  - Tehran results with a sponsored card and city banner;
  - profile (390);
  - `/dashboard/ads`;
  - builder (dark desktop, light phone);
  - `/platform/ads`.
- **Fixed during the visual check:**
  - banner arrows covered the headline;
  - card logos were clipped;
  - service labels broke mid-word;
  - platform-wide rows showed under a city's results.

## 3. Notes

- A refund after rejecting or suspending a paid campaign is manual in V1. The platform sees the paid date, and the reject dialog warns about it.
- Branding media (logo and cover) are stored under `tenants/{tenant_id}/…`, so that id appears in public image URLs, as it has since Phase 7. It is an opaque id with no power, but it goes to the Phase 15 hardening list. Ad images already use `ads/{public ref}/`.
- **Demo data** (`AdsDemoSeeder`, part of `DemoSeeder`):
  - 3 running banners and 1 running sponsored result;
  - «کافه نمونه» ("Kafe Nemooneh"): one approved campaign awaiting payment;
  - one campaign in the review queue;
  - two weeks of counters.
