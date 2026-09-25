# Phase 13: Marketplace (Plan)

Roadmap row (discovery §E.2):
- **Scope:** public store profiles (explicit public projection, no tenant-private data), categories, cities, search, featured listings.
- **Depends on:** phases 7 and 12.
- **Risk:** data leakage, handled with dedicated public read models.

## 1. Approach

- **New module `Marketplace`.** It depends on Core, Catalog, Commerce (delivery zones) and Billing (plan state, the featured add-on).
- **Opt-in:** `marketplace_listings`, one per tenant, tenant-owned:
  - `is_listed` (off by default; the owner chooses);
  - headline (≤ 120), about (≤ 600);
  - up to 3 categories and amenities from a code catalogue (`MarketplaceCatalog`);
  - price level 1–4;
  - platform moderation (`hidden_at`, `hidden_reason`) and a manual feature (`featured_until`).
- **Public read model:** `marketplace_stores`, one row per active branch of a listed café. This is the **only** table the public marketplace reads.
  - `ProjectStore` writes every column explicitly (allow-list), so nothing private can leak by adding a column somewhere else. Internal columns (`tenant_id`, `popularity`) are never serialised.
  - **Published:** name, branch, headline, about, city/province/address/public phone/coordinates, categories, amenities, price level, logo, cover, brand colour, weekly hours and time zone, services (dine-in, takeaway, delivery, online payment, pre-order), and up to 6 menu highlights (name, starting price, photo, taken from the public menu).
  - **Never published:** owner, staff, e-mail, settings, sales figures, plan, customers.
  - A normalised `search_text` (Persian/Arabic ی/ک, digits, ZWNJ) supports search.
- **Eligibility:** a café is shown only when all of these hold:
  - it is listed and not hidden by the platform;
  - the tenant can operate and the subscription is not read-only;
  - it has a name and a city on at least one active branch;
  - it has at least one product on its menu.
  - The owner's panel shows each missing requirement in plain words.
- **Freshness:**
  - model events (listing, branding, branch, hours, products and images, subscription, delivery zones) re-project that café after commit;
  - `marketplace:refresh` (hourly) re-projects everyone (plan expiry is time-based) and removes ineligible cafés;
  - popularity = log-bucketed orders over the last 30 days from `daily_metrics`, used for sorting only.
- **Featured:**
  - a Billing add-on «ویترین ویژه در بازارگاه» ("featured in the marketplace", feature `marketplace_featured`, available on every plan);
  - or a manual platform feature until a date;
  - always labelled «ویژه» ("featured"); featured cafés rank first within any filter.

## 2. API

**Public** (no tenant, `throttle:public`, cacheable):

| Endpoint | Returns |
|---|---|
| `GET /public/marketplace/home` | featured, newest, cities (with counts), categories (with counts) |
| `GET /public/marketplace/stores` | `q, city, category, amenities[], price, open_now, sort (relevance, nearest, newest), lat/lng, page`: paginated cards |
| `GET /public/marketplace/stores/{store}` | the profile: all listed branches, hours with open-now, highlights, storefront link |

**Owner** (`marketplace.manage`, owner and manager):
- `GET/PUT /marketplace/listing` (with an eligibility checklist and a preview of the public card).

**Platform:**
- `GET /platform/marketplace` (every listing and its status);
- `POST /platform/marketplace/{tenant}/hide|unhide|feature`.

## 3. Web

- **`/explore`:** hero with search (text, city) and category chips with icons; the featured row; «باز است» ("open now"), amenity and price filters; result cards (cover, logo, featured badge, open/closed, city, categories, price level); an empty state.
- **`/explore/[store]`:** profile with cover, logo, headline, about, amenities, branches (hours today, open now, address and a map link), highlights, and a «دیدن منو و سفارش» ("see the menu and order") call to action pointing to `/s/{slug}`.
- Dark mode and phone widths; SEO metadata; a sitemap for the marketplace; server-rendered with short revalidation.
- **Owner:** `/dashboard/marketplace` — listing toggle, fields, category and amenity pickers, price level, a live preview card and the checklist. It appears in the navigation and the command palette.
- **Platform:** `/platform/marketplace` moderation (hide/unhide with a reason, feature until a date).

## 4. Security

- Public endpoints read only `marketplace_stores` through a presenter with an explicit key list.
- Tests assert that responses contain no private data: no `tenant_id`, no internal IDs other than slugs, no e-mail, no owner phone, no settings, no sales numbers.
- Hidden, unlisted, read-only, suspended and incomplete cafés never appear (list, profile, home, search).
- Inputs are validated; search length is capped and not used as a regex.
- The owner endpoints go into the isolation harness.

## 5. Tests

- Projection and eligibility rules; re-projection on events.
- Search normalisation (ي/ی, ك/ک, Persian digits).
- Filters, open now, nearest, pagination.
- Featured ordering and labelling (add-on and manual).
- Moderation; the leak-proof response shape.
- Permissions and isolation.

## 6. Risks

- **Stale projection:** event-driven updates plus the hourly full refresh.
- **Leakage:** the allow-list projection, plus a test that walks every public response for forbidden keys.
