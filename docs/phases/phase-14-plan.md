# Phase 14: Advertising + a visual redesign of «کافه‌گردی» (Plan)

Discovery scope (§E): campaigns, creatives, placements, targeting, events, lightweight metrics, and clear sponsored / featured / organic labelling.

The owner also asked for «کافه‌گردی» to look much richer. Ads live on those screens, so both are done here.

## 1. Approach

### Advertising (new module `app/Modules/Advertising`, above Marketplace and Billing)

**Placements** (`ad_placements`, platform-level, seeded, prices editable by the platform):

| key | Where | Daily price (seed) | Capacity |
|---|---|---|---|
| `home_banner` | Banner carousel at the top of the «کافه‌گردی» home, and above results in the targeted city | 180,000 toman | 6 concurrent |
| `search_top` | «تبلیغ» cards at the top of search and category results | 90,000 toman | 12 concurrent |

**Campaigns** (`ad_campaigns`, tenant-owned, `BelongsToTenant`, ULID):

- **Fields:**
  - placement, start day, days (1–30);
  - target cities (the café's own branch cities; empty means every city);
  - one creative: headline ≤ 40, text ≤ 90, optional image for search, required for a banner (via `ImageProcessor`: WebP 1600 px plus a 800 px variant), call to action from a fixed list;
  - price quoted at submit;
  - a random public `ref`.
- **Lifecycle:** `draft` → `pending` (sent for review) → `approved` (awaiting payment) → `paid`. It can also be `rejected` (with a reason; edit and resend), `cancelled` (by the café, before payment) or `suspended` (by the platform, with a reason). A paid campaign is *scheduled*, *running* or *ended* by its dates.
- **Edits:** only in `draft` and `rejected`. Changing an approved campaign sends it back to review. A paid campaign is not editable.
- **Capacity:** overlapping paid campaigns per placement are checked at submit, approval and payment. If full, the café gets «این جایگاه در این بازه پر است؛ روز دیگری را انتخاب کنید» ("this placement is full for this period; choose another day").
- **Targets:** ads only link to the café's own «کافه‌گردی» profile or menu. There are no free URLs, so no phishing.

**Payment:** the same platform billing invoice and gateway flow (reviewed in Phase 12), generalised.

- `billing_invoices` gains the kind `ad`. `plan_id`, `cycle` and `mode` become nullable, and a `subject_id` column is added.
- Billing defines the contract `Billing\Contracts\InvoiceFulfiller` (kind → fulfil). Advertising binds its fulfiller.
- `markPaid` applies subscription kinds as before. Only subscription invoices void other open *subscription* invoices, so an ad invoice is never voided by a plan payment, and the reverse.
- The fulfiller re-checks, under lock:
  - the campaign is `approved` and the amount matches;
  - capacity is still free.

  If any check fails, the payment is kept and the campaign is marked `paid` with a flag for the platform to review (never charge twice or lose money silently).
- VAT is the billing rate. The return page sends the owner back to the ads screen.

**Serving** (via a contract in Marketplace, `Marketplace\Contracts\SponsoredContent`, with a null default binding and the Advertising implementation bound in its provider):

- **Banners:** live `home_banner` campaigns whose café is currently in the public projection, filtered by city when one is chosen. The order rotates every few minutes, up to 6.
- **Sponsored results:** among the stores that match the visitor's search (so an ad is always relevant), up to 2 live `search_top` campaigns go first with the «تبلیغ» label. They are not repeated in the organic list.
- **Labels:** «تبلیغ» (paid campaign), «ویژه» (featured add-on), nothing for organic. The API sends `sponsored` blocks separately from organic data.
- **Public shape:** an allow-list in `AdPresenter` (no tenant or campaign ids). Each ad carries a short-lived HMAC event token.

**Events and metrics (lightweight):**

- `POST /public/ads/events {token, type: impression|click}`, sent from a Server Action with the visitor IP forwarded.
- The token is `ref.placement.expiry.hmac`, valid for 2 hours.
- **Dedupe:** one impression per visitor per ad per hour, one click per visitor per ad per day (hash of IP + user agent + ref in the cache). The endpoint is throttled.
- Counts go to `ad_daily_stats` (campaign, Tehran day, impressions, clicks) with atomic increments. No raw event rows.
- The web counts an impression only when at least 50% of the ad is visible for 1 second, and a click on navigation.

**Café panel** `/dashboard/ads` (new permission `ads.manage`: owner and manager):

- KPIs: impressions, clicks, CTR, spend, live campaigns.
- Daily chart.
- Campaign list with status timeline and review notes.
- Campaign builder: placement cards with prices, Jalali start day, days, cities, creative, live preview of the real banner or sponsored card, a price quote, then send for review → pay once approved.
- Dashboard widget `ads_performance`.

**Platform** `/platform/ads`:

- Review queue with creative preview: approve, or reject with a reason.
- All campaigns: suspend or resume, stats.
- Placement prices and capacity.

### «کافه‌گردی» visual redesign (web only; the API gains nothing it doesn't need)

- **Hero:**
  - layered and illustrated: a token-coloured pattern of café icons, a soft glow, a larger headline with an accent word;
  - one elevated search panel that holds the search and «کجا؟» ("where?") together;
  - small live counters (cafés, cities, open now).
- **Banner carousel** under the hero:
  - sponsored banners, plus a platform slide inviting cafés when there are no ads;
  - swipe on phones, arrows and dots on desktop, auto-advance that pauses on hover or when the page is hidden, reduced-motion aware.
- **Category rail:** large round illustrated tiles (icon in a tinted disc, label, count) instead of small pills.
- **Cards:**
  - **Generated cover art** when a café has no photo: its brand colour as a gradient, a pattern of its category's icons, and a large monogram. Every café looks distinct, not the same grey cup.
  - Monogram avatar when there is no logo.
  - Clearer hierarchy: name, district, price, badges.
- **Row types differ, not seven identical grids:**
  - featured → wide spotlight cards with the text over the cover;
  - collections → snap carousels with desktop arrows;
  - popular → a ranked "Top" list with large numerals;
  - cities → art tiles.
  - A collection that adds no café not already shown is skipped.
- **Profile:**
  - a generated cover when there is none;
  - an overlapping logo;
  - a sticky order bar on phones;
  - menu highlights use the storefront `ProductVisuals` illustration fallback instead of a grey cup;
  - a «کافه‌های مشابه» ("similar cafés") row.
- **Footer** with a café-owner call to action.
- Everything from `@cafe/ui` tokens; dark mode; a 390 px check.
- **Token fix found while planning:** `--color-on-danger` is missing from the forced-dark block.

## 2. API

- **Café** (`ads.manage`): `GET /ads` (summary, campaigns, placements, stats), `POST /ads/quote`, `POST /ads/campaigns`, `PUT /ads/campaigns/{id}`, `POST /ads/campaigns/{id}/image`, `POST …/submit`, `POST …/cancel`, `POST …/pay` (opens the billing invoice and returns the gateway URL), `GET /ads/campaigns/{id}/stats`.
- **Platform:** `GET /platform/ads`, `POST /platform/ads/{id}/approve|reject|suspend|resume`, `GET|PUT /platform/ads/placements/{key}`.
- **Public:**
  - `home` gains `banners`;
  - `stores` gains `sponsored` (only on page 1);
  - profile gains `similar`;
  - `POST /public/ads/events`.

## 3. Web

- `/dashboard/ads` (page, builder, preview) and the widget.
- `/platform/ads` (queue, table, placements).
- Explore redesign: `ExploreParts`, `ExploreClient`, `page.tsx`, `[store]/page.tsx`, the layout footer.
- The billing return page picks its destination from the invoice kind.

## 4. Security

- **Café endpoints:** tenant-scoped; `ads.manage`; every one in the isolation harness.
- **Platform endpoints:** `actor:platform` only.
- **Public data:** the public projection only; allow-list presenters; no ids. Images are re-encoded and stripped.
- **Links:** the target is always the café's own path, built server-side.
- **Events:** HMAC-signed, expiring tokens; dedupe and throttle; counts can't be written for ads that weren't served; no personal data stored (only hashed dedupe keys, in the cache, with a TTL).
- **Payment:** the reviewed billing flow; the amount comes from the stored campaign quote, never from the client; exactly once under the invoice lock; ad and subscription invoices can't void each other.
- **Review:** only approved campaigns can be paid; nothing is served before payment and review; a suspension takes effect immediately.
- **Read-only subscriptions:** can't create campaigns (existing `ResolveTenant` rule).

## 5. Tests

- **Lifecycle:** create, edit rules, submit, reject → edit → resubmit, approve, pay (fake gateway), running only inside the dates, suspend hides the ad immediately.
- **Capacity:** full at submit and at payment.
- **Billing isolation:** paying a plan doesn't void an open ad invoice, and the reverse; an ad invoice doesn't touch the subscription; amount tampering is impossible; verify is idempotent.
- **Serving:**
  - a banner only for a live, paid café still in the projection;
  - city targeting;
  - sponsored only among matching stores, max 2, deduped from organic;
  - labels;
  - no internal ids in the payload.
- **Events:** a valid token counts once per hour and per day; forged or expired tokens are rejected; throttled.
- **Isolation harness:** every new tenant endpoint.
- **Permissions:** `ads.manage`.
- **Platform:** a staff actor gets 403.
- **Web:** typecheck, lint and build; visual checks (explore home and results, desktop and 390 px, light and dark; profile; ads panel; platform queue).

## 6. Risks

- **Ad fatigue on a small marketplace:** capacity caps, max 2 sponsored per page, and banners only if at least one live one exists (otherwise the platform slide).
- **Click fraud:** lightweight dedupe only. Campaigns are priced per day (not per click), so fraud can't raise a café's bill.
- **Refunds after a suspension:** handled manually by the platform in V1 (noted in the report).
