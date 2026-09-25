# Phase 13: Marketplace «کافه‌گردی» (Report)

Plan: `phase-13-plan.md`. Status: done, awaiting approval.

## 1. What was built

### Module `Marketplace`

- **`marketplace_listings`** (tenant-owned): the café's opt-in (`is_listed`, off by default), headline, about, up to 3 categories and any amenities from a code catalogue (`MarketplaceCatalog`: 11 categories, 10 amenities, price levels 1–4), and platform moderation (`hidden_at` + reason, `featured_until`).
- **`marketplace_stores`**: the only table the public marketplace reads. It is a platform-level public read model with one row per active branch of an eligible café.
  - `ProjectStore` writes every column explicitly from public facts only: name, branch, headline, about, city/province/address/public phone/coordinates, categories, amenities, price level, logo/cover URLs, brand colour, weekly hours and time zone, services (dine-in, takeaway, delivery, online payment, pre-order), up to 6 menu highlights, and normalised `search_text`.
  - `tenant_id` and `popularity` are internal.
  - `StorePresenter` serialises through explicit key lists.
- **Eligibility.** All required items must pass, and the owner sees each one in plain words:
  - listed;
  - not hidden by the platform;
  - tenant operating and subscription not read-only;
  - a city on at least one active branch;
  - at least one active product.

  Logo, cover and headline are recommended, not required.
- **Freshness:**
  - model events (listing, branding, branches, hours, products, images, prices, tables, delivery zones, subscription and add-ons, tenant) mark the café in `MarketplaceSync`, which re-projects it once when the request ends;
  - `marketplace:refresh` runs hourly and catches time-based changes (a subscription running out, a feature expiring, popularity);
  - popularity is log-bucketed from 30 days of `daily_metrics` and used only for ranking.
- **Search** (`SearchText`): Arabic ي/ك → Persian ی/ک, Persian/Arabic digits → Latin, ZWNJ and diacritics removed. Up to 6 terms; each term must match, with the LIKE input escaped.
- **Featured:**
  - the Billing add-on «ویترین ویژه در بازارگاه» ("featured in the marketplace", 390,000 T/month, every plan; valid while the subscription runs);
  - or a platform feature until a date;
  - always ranked first within any filter and labelled «ویژه» ("featured").
- **Public API** (no tenant, `throttle:public`, `Cache-Control: public, max-age=60`):
  - `home`: featured, popular, newest, cities, categories, amenities, total;
  - `stores`: text, city, category, amenities, price, open now, sort by relevance/nearest/newest, location, page;
  - `stores/{slug}`: profile with every branch's hours and open state.
- **Owner:** `GET/PUT /marketplace/listing` with the checklist, a preview card and the public path. New permission `marketplace.manage` (owner, manager).
- **Platform:** list every listing and its live branches; hide with a reason, unhide, feature until a date.
- **Demo:** `MarketplaceDemoSeeder` lists «کافه نمونه» and adds 7 small fictional cafés in Tehran, Shiraz, Isfahan, Mashhad and Tabriz (menus, hours, coordinates).

### Web

- **`/explore`** («کافه‌گردی», public):
  - hero with the café count and a search form (text + city; works without JavaScript);
  - category chips with icons and counts;
  - home rows (featured, popular, cities, and newest when there are more than 8 cafés);
  - results with filters: open now, near me (browser location), amenities, price, sort;
  - pagination and a calm empty state.
- **Cards:** cover, else a menu photo, else a placeholder icon in the café's own brand colour (always via `brandCss`, contrast-checked for both themes). Plus logo, «ویژه» badge, open state with the next opening time, price-level dots, city, distance and categories.
- **`/explore/[slug]`:**
  - cover and a floating header card: logo, name, headline, categories, price, and «دیدن منو و سفارش» ("see the menu and order", linking to the storefront);
  - service chips, about, menu highlights and amenities;
  - branch cards: open state, today's hours, the week table, `tel:` link, directions;
  - SEO metadata and Open Graph image.
- **`/`:** staff are sent to the dashboard, everyone else to `/explore`.
- **SEO:** `sitemap.xml` (marketplace and every profile); `robots` allows `/explore` and closes `/platform`, `/print`, `/billing`.
- **Panel «بازارگاه»** ("Marketplace", `/dashboard/marketplace`): the listing switch, headline and about with counters, category picker (max 3), amenity chips, price-level control, the checklist with links to where to fix things, and a live preview of the public card. It appears in the navigation and the command palette.
- **`/platform/marketplace`:** moderation table (live / incomplete / off by the café / stopped with reason), featured-until date, and actions (view, feature until a Jalali date, stop, show again).

## 2. Verification

- API: 472 tests green. `tests/Feature/Marketplace/MarketplaceTest.php`, 4 scenarios:
  1. Opt-in and eligibility checklist; home counts; search by a menu item, by Arabic letters without ZWNJ, and a miss; profile address, phone, highlights and services; a name change through the panel is visible at once. **Leak check:** home, search and profile contain no tenant ID, branch ID, owner phone, `id`, `tenant_id`, `search_text`, `popularity`, e-mail, password, merchant, subscription or settings.
  2. Hidden by the platform (with the reason shown to the owner); unlisted by the owner; an empty menu; an expired subscription. Each removes the café from list, profile and home.
  3. Filters (city, category, amenity, price); nearest with distance; open now against a schedule; featured by platform date and by the add-on, ranked first and labelled; pagination.
  4. Permissions (cashier refused, manager allowed, platform endpoints refuse owners); validation (4 categories, unknown values, duplicates, price, length); public input limits (unknown category, long query, `%_\` treated literally, lat without lng, a path-traversal slug).
- Isolation harness: the listing endpoints were added.
- Larastan level 6: 0 errors. Pint clean.
- Web: typecheck, lint and build green. `@cafe/ui` 6 and `@cafe/locale` 8 tests green.
- Visual check (headless Chrome, with the demo cafés):
  - `/explore` home (light, desktop) and dark at 390 px with no horizontal overflow;
  - city results with filters;
  - the «کافه نمونه» profile (its own brand colour, two branches, one closed until 16:00 on Friday);
  - Eram profile, dark at 390 px;
  - owner panel with preview;
  - platform moderation (dark).
- Fixed during the check:
  - the "newest" row repeated the popular one on a small marketplace;
  - a branch without hours read «هر روز» ("every day"; now «باز (ساعت کاری ثبت نشده)», "open, no hours set");
  - the cover gradient was shown with no cover.

## 3. Notes

- Nothing private is reachable by design: the public API can only read the projection, and the projection is built from an allow-list.
- Branch phone and address are shown because the café published them for its storefront. Owners control them in «شعبه‌ها» ("Branches").
- **Deploy:**
  - schedule `marketplace:refresh` hourly;
  - `STOREFRONT_URL` is used for sitemap URLs;
  - run `php artisan marketplace:refresh` once after migrating.
- Ratings and reviews are not part of V1 (decision D5 left only an order rating, which isn't built yet). The card and profile have room for it.
