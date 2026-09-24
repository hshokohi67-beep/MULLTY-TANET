# Phase 7: Storefront (Implementation Plan)

**Status:** implemented (see `phase-07-report.md`)
**Depends on:** Phases 2–6c, all approved (6c approved on 2026-09-24).
**Roadmap scope:** a mobile-first RTL public storefront per tenant covering:
- the menu and a product sheet with modifiers;
- cart and checkout, with address, map pin and zone check;
- payment, and tracking;
- the customer account (wallet, points, tier, history, reorder, addresses);
- QR table ordering;
- a PWA manifest, SEO and a11y.

## 1. Principles
- **BFF only.** The browser never talks to the API. Next.js server code (Server Components, Server Actions, Route Handlers) calls it.
- **Per-tenant tokens in HttpOnly cookies scoped to `/s/{tenant}`:**

  | Cookie | Holds |
  |---|---|
  | `cs_cart` | the cart token |
  | `cs_cust` | the customer token |
  | `cs_table` | the table-session token |
  | `cs_branch` | the chosen branch slug (not secret) |

  Because the path is scoped, two cafés open in the same browser never see each other's tokens.
- **Keep the menu static and fast.** The menu and product pages don't read cookies, so they are ISR (60 s). Cart, customer and table state load in small client islands through Server Actions. A shopper on a slow Android phone gets HTML at once, and nothing personal is ever cached.
- **Real client IP.** The BFF forwards the visitor's IP in `X-Forwarded-For`. The API already trusts `TRUSTED_PROXIES`, which must list the web server's address. Without this, every shopper would share one rate-limit bucket (the web server's IP).
- **Brand colour.** The tenant's `primary_color` becomes `--brand`. `--on-brand` is picked for contrast (white or near-black by relative luminance), so any brand colour stays readable.
- Everything else follows the existing conventions: Persian/RTL, `@cafe/ui`, `@cafe/locale`, no native date/time inputs, and "•" as separator.

## 2. API additions (small; most storefront endpoints exist since Phase 3)

| Endpoint | Purpose |
|---|---|
| `GET /public/storefront` | One cached call for the shell. Returns:<br>• tenant profile and branding;<br>• contact (phone, Instagram);<br>• active branches with address, location, today's hours, `is_open`, `next_opening_at` and whether delivery is offered;<br>• features: online payment available, club enabled, wallet payments, preorders when closed. |
| `POST /public/delivery/check` `{branch_id, latitude, longitude}` | Zone check for the map pin before an address is saved: zone name, fee, ETA and minimum order, or a Persian "outside the delivery area" message. Throttled. |
| `POST /public/cart/reorder` `{order_id}` (`X-Cart-Token` + customer token) | Copies a past order's lines (variant and modifiers) into the cart. Returns the new quote plus the lines that were skipped because they are no longer sold. The customer may use only their own orders. |
| `GET /customer/orders` | Adds each order's `tracking_token`, so history can link to live tracking (these are the customer's own orders). |

- **Wallet at checkout** needs no new endpoint. The BFF orchestrates it:
  1. Place the order: `online` intent if the rest will go to the gateway, otherwise `cash`.
  2. Pay from the wallet (`/customer/orders/{id}/wallet-payment`, idempotent).
  3. If anything is still due and online was chosen, start the gateway.

  A wallet that covers the whole total releases an online-intent order straight to the kitchen (existing Phase 5 behaviour).
- **Product pages and the sitemap** are built from the cached public menu (it already carries slugs), so there is no extra endpoint.

## 3. Web: routes (`apps/web/src/app/s/[tenant]/…`)

| Route | Rendering | Content |
|---|---|---|
| `/s/{t}` | ISR 60 s | Hero (logo, name, open/closed, branch), sticky category chips with scroll-spy, search, featured row, product cards (image, price from, unavailable state), a product sheet opened in place, and a floating cart bar |
| `/s/{t}/p/{slug}` | ISR 60 s | The same product sheet as a real page (SEO, sharing), with JSON-LD `Product` + `BreadcrumbList` |
| `/s/{t}/cart` | dynamic | Lines with quantity and notes, per-line problems, coupon, order type (takeaway/delivery; table mode is fixed), address picker with zone result, scheduled preorder (Jalali day + `ClockSelect`), payment (cash/online plus the wallet toggle), a totals breakdown, and place order |
| `/s/{t}/login` | dynamic | OTP in two steps (phone → code, with a resend timer), then back to `next` |
| `/s/{t}/account` | dynamic | Club card (tier, progress to the next tier, wallet, points and their value, redeem), referral code with share/copy, profile (name, Jalali birthday), addresses, orders with tracking/reorder, logout |
| `/s/{t}/account/addresses/new`, `/[id]` | dynamic | Address form with map pin (Leaflet, tile URL from env), "my location" (geolocation), and a live zone check |
| `/s/{t}/t/{qr}` | dynamic | Joins the table session, sets `cs_table`, strips the token from the URL (redirect) and opens the menu in table mode |
| `/s/{t}/track/{order}` | client | Existing tracker, now inside the storefront shell (token stays in the fragment) |
| `/s/{t}/pay/{payment}` | dynamic | Existing result page, inside the shell |
| `/s/{t}/manifest.webmanifest` | route | Per-tenant PWA manifest (name, colours, logo icons, start_url, scope) |
| `/s/{t}/sitemap.xml` | route | Home + product URLs, from the cached menu |
| `/robots.txt` | root | Allows `/s/`; disallows dashboard, KDS, login, cart, account and tracking |

**Table mode:** a table banner (table label and branch) with «صدا زدن گارسون» ("call the waiter") and «درخواست صورت‌حساب» ("request the bill"). The cart is created as `qr_table`, and a guest can check out without logging in.

## 4. SEO & a11y
- **Metadata:** title/description from branding, canonical, OpenGraph (logo), `fa_IR`.
- **Indexing:** the account, cart, login, table and tracking pages are `noindex`.
- **JSON-LD:** `CafeOrCoffeeShop` with address, geo, `openingHoursSpecification`, telephone and menu URL. Products and breadcrumbs as in §3.
- **A11y:**
  - landmarks and a skip link; category chips as a `nav` with `aria-current`;
  - the product sheet is a native `<dialog>` with focus trap and Esc;
  - the quantity stepper has labelled buttons and 44 px targets;
  - live region for cart updates; visible focus; reduced-motion respected;
  - price and total read out in words order (tabular numerals).

## 5. Deferred (with reason)
- **Web Push opt-in:** it needs a VAPID key per tenant, a service worker, and the notification pipeline. It moves to the **Notifications** work, which also covers SMS templates and order-status pushes. The manifest and installability ship now.
- **Custom domains** (`X-Tenant-Domain` → storefront rewrite): the storefront works on `/s/{slug}` now. The host → slug rewrite in `proxy.ts` comes with Phase 12/13 domain management.
- **Realtime (Reverb) for tracking:** tracking keeps the cheap 5 s polling with pause-when-hidden.

## 6. Security
- All tokens live in HttpOnly, `SameSite=Lax`, path-scoped cookies. Customer tokens are tenant-bound (`TenantBoundTokenable`, since Phase 5), so a `cs_cust` cookie replayed against another tenant is refused.
- Server Actions validate the tenant slug and IDs before calling the API. Checkout uses an idempotency key per form render (`useSubmissionKey`).
- `reorder` checks ownership (someone else's order returns 404), and `delivery/check` is throttled.
- No personal data is put in URLs. The QR token is removed from the address bar by a redirect, and the page sets `Referrer-Policy: no-referrer`.
- The OTP code is never logged by the web server.

## 7. Tests
- **API:**
  - storefront shell: only active branches, open/closed, delivery flag, no internal fields;
  - delivery check: in/out of zone, not configured, validation;
  - reorder: copies lines and modifiers, skips unavailable lines, rejects others' orders, requires a customer;
  - customer orders include the tracking token.
- **Isolation harness:** the new endpoints get cross-tenant cases (another tenant's branch, order and cart).
- **Web:**
  - typecheck, lint, build;
  - locale tests;
  - a unit test for the brand-contrast helper;
  - Chrome visual checks of the menu, product sheet, cart and checkout (guest table mode), tracking, and the account UI (rendered with a test customer via the BFF).

  No codes or tokens are typed in the browser.

## 8. Risks
- **Map tiles in Iran:** OSM may be slow. The tile URL is configurable (`NEXT_PUBLIC_MAP_TILE_URL`, e.g. Neshan/Map.ir later), and the address form still works without the map (the zone check then needs the pin, with a clear message).
- **Menu size:** a large menu is one JSON payload. Images are lazy-loaded with explicit sizes (no layout shift).
