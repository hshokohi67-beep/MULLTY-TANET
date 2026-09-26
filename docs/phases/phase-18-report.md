# Phase 18 report: browser notifications, self-service signup, «کافه‌یار برای کسب‌وکارها»

## What shipped

### Browser notifications (Web Push)
**Crypto** (`App\Support\Push`, `ext-openssl` only; packagist was unreachable, and the standard needs no library):
- VAPID ES256 JWT (RFC 8292);
- `aes128gcm` payload encryption (RFC 8291);
- an endpoint allow-list against SSRF: FCM, Mozilla, Apple, Windows;
- `php artisan push:vapid` for the key pair;
- Windows dev falls back to PHP's bundled `openssl.cnf`.

**Module `Notifications`:**
- **Storage:** `push_subscriptions`. Endpoint, keys and URL are encrypted; the endpoint's HMAC hash finds a returning browser.
- **Delivery:** a queued `SendPush` job after commit. 404/410 deletes the subscription; so do 5 failures in a row.
- **Customers:**
  - On the tracking page, «وقتی آماده شد خبرم کن» ("tell me when it's ready") subscribes that browser to that order, authorised by the order's tracking token.
  - They are notified when the order is accepted, ready (takeaway, online and phone orders), out for delivery, or cancelled/rejected.
  - Tapping opens the tracking page.
- **Staff:**
  - «اعلان سفارش تازه روی این دستگاه» ("new-order alerts on this device") in the panel's bell. Needs `orders.view`, re-checked on every send, so a disabled member gets nothing.
  - They get «سفارش تازه #n» for each placed order; tapping opens the order.
- **Payloads:** order number, café and status only. Lock screens are public, so no names or items.

**Web:**
- `public/sw.js`: push and click only, and it only opens pages of its own site. It is served on café subdomains too (proxy pass-through) with `Cache-Control: no-cache`.
- Clear messages for unsupported browsers, denied permission, and iPhone (it must be added to the home screen first).

### Self-service signup (`/signup`)
**Step 1:**
- café name;
- the address, suggested from the Persian name (`SlugSuggester`: a dictionary for common words such as کافه/رستوران, a word-final «ه» read as "e", `-2`/`-3` when taken, reserved names refused), checked live;
- owner name, mobile, and a password (8+ characters, letters and digits).

**Step 2:** the SMS code from the platform line. `OtpService` now takes a scope, `signup`, besides tenants.

**Then:**
- `CreateTenant` runs: trial, first branch, subdomain, default roles.
- The owner is signed in and lands in the panel, where the setup checklist is.

**Guards:**
- `SELF_SIGNUP` switch;
- throttle: 6 per minute and 30 per day per IP;
- honeypot field;
- an existing phone is sent to «ورود» ("sign in");
- the address is re-checked at submit (unique-constraint race included);
- a wrong code costs an attempt, so it is checked last.

**Entry points:**
- «کافه یا رستوران دارید و هنوز حساب ندارید؟ شروع رایگان» on the login page;
- links in the «خوراک‌گردی» header, footer and owner band.

### «کافه‌یار برای کسب‌وکارها» (`/business`)
Reuses the landing-page frame (scroll reveals, glow texture, display type). It works in both themes and on phones.

**Sections:**
- **Hero:** a phone showing a menu, two notification cards, and a CTA to `/signup`.
- **"At a glance" strip.**
- **Six spotlights, each with a token-drawn mock:**
  - menu and dedicated site;
  - orders and QR;
  - kitchen and notifications;
  - club and SMS;
  - stock and cost of goods;
  - reports.
- **«خوراک‌گردی»:** discovery and ads.
- **Six smaller features:**
  - staff and payroll;
  - multi-branch;
  - «خوراک‌گردی»;
  - roles;
  - Persian help;
  - built for Iran.
- **Three-step start.**
- **Pricing:** from the new `GET /public/plans`, with a monthly/yearly switch. Custom domain and the marketplace add-on are hidden: not offered yet, or sold separately.
- **FAQ, final CTA, footer.**

**Every claim was checked against the product:**
- 5 SMS panels;
- read-only (not deleted) after the trial;
- money goes straight to the café's gateway;
- per-branch prices and stock.

The mocks use sample data. There are no invented customer counts or quotes.

## API
- `GET /public/push/key`
- `POST /public/orders/{trackedOrder}/push` (`X-Order-Token`)
- `POST /push/subscription`, plus `/status` and `/remove` (staff, `orders.view`; added to the isolation test)
- `GET /public/signup/slug`, `POST /public/signup/otp`, `POST /public/signup`
- `GET /public/plans`

## Checks
- **Unit (`WebPushCryptoTest`):**
  - a browser-side decrypt of what we send, where a wrong auth secret fails;
  - the VAPID JWT verifies against our public key;
  - endpoint allow-list and deny-list.
- **Feature:**
  - `PushTest` (3): tracking-token gate, URL and endpoint validation, one row per browser, the right statuses pushed, encrypted body, 410 deletes, push off means nothing stored, staff permission and disabled member;
  - `SignupTest` (4): suggestions, the full signup (trial, branch, working token), guards, public plans;
  - isolation for the staff push routes.
- **Totals:** 561 tests passed, 1 skipped. Larastan level 6: 0 errors. Pint clean. Web typecheck, lint and build clean.
- **Visual:**
  - `/business` at 1280 px (light) and 390 px (dark): hero, spotlights, pricing, FAQ;
  - `/signup` at 390 px (light, with a live address suggestion) and at 1280 px (dark);
  - the panel bell with the device toggle.

## Notes for the owner
- **Push from Iran:** Chrome delivers through Google's FCM. A server inside Iran may not reach `fcm.googleapis.com`, and visitors' Chrome needs FCM too. Firefox and Safari use their own services.
  - Failures are quiet: logged, and the subscription is dropped after 5.
  - Test on the real host before promising it to cafés. SMS stays the reliable channel.
- **Keys:** run `php artisan push:vapid` once on the server and put the three values in `.env`. Changing keys later drops every subscription.
- **Plan tagline:** the chain plan's tagline mentions «دامنه‌ی اختصاصی» ("custom domain"). Edit it in the platform panel (Plans) until custom domains are offered.
- **Not tested end to end:** the signup UI with a real SMS code. By policy the code is never typed by the assistant; the API tests cover the flow. The first real signup on the server is the live check.
