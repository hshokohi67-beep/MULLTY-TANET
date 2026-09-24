# Phase 7: Storefront (Completion Report)

**Date:** 2026-09-25
**Plan:** `phase-07-plan.md`
**Status:** Implemented, awaiting approval. The browser visual pass is still to do (see §3).

## 1. What was built

### API (Commerce module)

| Endpoint | What it does |
|---|---|
| `GET /public/storefront` | The shell in one cached call:<br>• public profile, branding and contact;<br>• active branches with weekly hours, `is_open`, `next_opening_at` (UTC) and whether delivery is offered;<br>• features: online payment, club, wallet payments, preorders when closed.<br>It is an explicit allow-list. |
| `POST /public/delivery/check` | Map-pin zone check before an address is saved. Returns the zone, fee, free-delivery threshold, minimum order and ETA, or the Persian "outside the area" / "no delivery" error. It has its own limiter (30/min). |
| `POST /public/cart/reorder` | "Order again": `ReorderIntoCart` copies the lines, variants, still-active modifiers and notes into the cart. Lines whose product or variant is gone or switched off are skipped and named. Only the customer's own orders are allowed (others' orders return 404), and guests get 401. |
| `GET /customer/orders` | Now includes each order's `tracking_token`, so history can link to live tracking. |

### Web (`/s/{tenant}`)

**Architecture**
- It is a BFF: the browser never calls the API.
- Cart, customer and table tokens are HttpOnly cookies scoped to `/s/{tenant}` (`cs_cart`, `cs_cust`, `cs_table`, `cs_table_info`). Two cafés open in one browser can't see each other's session.
- The visitor's IP is forwarded (`X-Forwarded-For`), so rate limits apply per shopper. `TRUSTED_PROXIES` must include the web server.
- Menu and product HTML never contain personal data. The cart, table and login load in a client island (`StoreProvider` → `loadSession`).

**Brand colour.** The café's `primary_color` becomes the storefront's brand tokens in both themes (`brandCss`/`brandTokens` in `@cafe/ui`):
- it is darkened or lightened until it reaches 3:1 against the page;
- the text on it is picked by WCAG contrast.

**Pages**

| Page | Contents |
|---|---|
| **Menu** | Open/closed badge with the next opening time, branch switcher, sticky search, category chips with scroll-spy, a "پیشنهاد ما" ("our picks") row, product cards (photo or brand initial), one-tap add for simple items, and a bottom-sheet product view |
| **Product sheet / page** (`/p/{slug}`) | Sizes; add-on groups with live min/max rules (the add button explains what is still required); quantity; note; live price. As a page it has JSON-LD `Product` + `BreadcrumbList` and a canonical URL |
| **Cart & checkout** | Quantity steppers with per-line problems; pickup/delivery switch (lines are copied into a new cart of the new type); saved-address choice with zone and ETA; "now" or preorder (Jalali day + `ClockSelect`); coupon; online or cash payment; wallet toggle with its balance; live server totals and issues; one idempotency key per attempt |
| **Checkout orchestration** | Place the order → optionally pay from the wallet → if anything is still due online, go to the gateway. Otherwise the customer goes to live tracking. Each step is idempotent |
| **QR table mode** | `/t/{qr}` is now a route handler: it joins the session, sets the cookies and redirects at once (the token leaves the address bar). A table banner offers «صدا زدن گارسون» ("call the waiter") and «درخواست صورت‌حساب» ("request the bill"), and a guest checkout collects an optional name |
| **Login** | Two steps, mobile number then SMS code, with a resend countdown, `one-time-code` autocomplete and Persian digits accepted. Only known `next` targets are allowed (no open redirect) |
| **Account** | Club card (tier, wallet, points and their value, progress to the next tier); points → wallet; referral code copy/share and entering a friend's code; orders with status, **track** and **order again**; addresses (add/edit/delete); profile (name, Jalali birthday set once, SMS opt-in); logout |
| **Addresses** | Iranian address form, a Leaflet map pin (tap or drag, "my location"), and a live zone check with fee and ETA. The tile server is set by `NEXT_PUBLIC_MAP_TILE_URL` (OSM by default) |
| **Tracking & payment result** | Now inside the storefront shell |

**Installability and SEO**
- Per-café `manifest.webmanifest` (name, RTL, scope, brand theme colour, logo) and `viewport.themeColor`.
- `sitemap.xml` per café (branches + product pages).
- A root `robots.txt`: storefronts are allowed; the cart, account, login, tracking, payment and QR pages are disallowed and `noindex`.
- JSON-LD `CafeOrCoffeeShop` with address, geo, `openingHoursSpecification`, telephone and menu.

**A11y**
- Landmarks and the skip link; the category `nav` uses `aria-current`.
- The sheet is a native `<dialog>` (focus trap, Esc). The Dialog gained a `sheet` variant, which is a centred modal on wide screens.
- 40–44 px targets; labelled steppers; a polite live region for "added to cart"; reduced motion respected.

## 2. Verification

| Check | Result |
|---|---|
| `php artisan test` | **344 passed** (3459 assertions). New `StorefrontTest` (5):<br>• shell: branches, hours, open/closed in Tehran time, delivery flag, no internal fields;<br>• delivery check: in and out of zone, not configured, validation;<br>• reorder copies lines and modifiers and skips switched-off products and variants;<br>• reorder by another customer is a 404, and by a guest a 401;<br>• history includes the tracking token. |
| Isolation harness | 108 tests (1295 assertions):<br>• A's shell never lists B's branches;<br>• B's branch can't be zone-checked at A;<br>• B's customer token is refused at A;<br>• A's customer can't reorder B's order. |
| Larastan 0 • Pint | passed |
| Web | Typecheck, ESLint and build are clean. `@cafe/locale` tests pass (8). New `@cafe/ui` brand tests pass (5): invalid colours, contrast in both themes, text colour choice. |
| Server smoke test | 200 for the menu, cart, login, manifest and sitemap. `/p/{unknown}` returns 404, and `/account` without login redirects (307). The rendered HTML contains the brand CSS, the JSON-LD (`CafeOrCoffeeShop`, `Product`, `AggregateOffer`, `BreadcrumbList`), the closed-now notice with the next opening in Persian, and the branch switcher. |

**Found and fixed while checking:** an add-on group with `max_select = 0` means "no limit" in the server's pricer. The picker had treated it as "none allowed"; it now follows the server rule.

## 3. Not done yet / notes
- **Visual check in Chrome:** not done. Every browser call this session was refused by the extension ("Could not verify this site's safety category"). The screens are checked at the server and HTML level only; the visual pass (menu, sheet, cart, table mode, account, dark mode, phone width) is the first thing to do once the browser works.
- **Web Push:** deferred to the Notifications work, as planned. Custom domains move to Phase 12/13, and tracking keeps the 5 s polling.
- **Time zone:** preorder times are sent as Tehran wall-clock time with the fixed `+03:30` offset. Iran has had no DST since 2022, and tenants outside Iran are not in scope.
- **Map tiles:** OSM may be slow in Iran. Set `NEXT_PUBLIC_MAP_TILE_URL` to a local provider. Without tiles, "my location" still fills the pin.
- **Production config:** `TRUSTED_PROXIES` must list the Next.js server, or every shopper shares one rate-limit bucket.
