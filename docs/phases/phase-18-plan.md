# Phase 18 plan: browser notifications, self-service signup, «کافه‌یار برای کسب‌وکارها»

The owner asked for two things:
- browser (web push) notifications;
- a rich sales landing page that sells the subscription, reached from a «کافه یا رستوران دارید؟» ("have a café or restaurant?") button.

The landing page's main button needs somewhere real to go. Cafés can't sign up today (only the platform admin creates tenants), so this phase also adds self-service signup with a 14-day trial.

## 1. Web Push
**Why:** «سفارشت آماده است» ("your order is ready") without SMS cost, and «سفارش تازه» ("new order") for the café's staff even when the panel tab is in the background.

### Crypto
- **Where:** `App\Support\Push`, with no third-party library (packagist is unreachable from here; `ext-openssl` covers it).
- **VAPID:** an ES256 JWT (`aud` = the push service origin, `exp` 12 h, `sub` = the platform contact).
- **Payload:** RFC 8291 `aes128gcm`: ephemeral P-256 ECDH, HKDF-SHA-256 (auth secret, then salt), AES-128-GCM, a single record.
- **Endpoint allow-list, to prevent SSRF:**
  - `fcm.googleapis.com`
  - `updates.push.services.mozilla.com`
  - `*.push.apple.com`
  - `*.notify.windows.com`
  - `web.push.apple.com`
- **Keys:** `php artisan push:vapid` prints a new key pair for `.env` (`WEBPUSH_PUBLIC_KEY`, `WEBPUSH_PRIVATE_KEY`, `WEBPUSH_SUBJECT`). Without keys, push is simply off.

### Module `Notifications`
**Table `push_subscriptions`** (tenant-owned):
- `audience`: `customer` or `staff`;
- `user_id` (staff), `order_id` (customer, composite FK), `customer_id` (nullable);
- `endpoint` (encrypted), `endpoint_hash` (unique per tenant), `p256dh` and `auth` (encrypted);
- `failures`, `last_sent_at`, timestamps.

**Who gets what:**
- **Customer:** on the order tracking page, «وقتی آماده شد خبرم کن» ("tell me when it's ready") subscribes this browser to that order. The request is authorised by the order's tracking token and nothing else.
  - Notifications: ready (takeaway, online and phone orders), out for delivery, delivered, cancelled or rejected.
  - Tapping one opens the tracking page.
- **Staff:** in the panel, «اعلان سفارش تازه روی این دستگاه» ("new-order alerts on this device") subscribes this browser for the signed-in member.
  - They get «سفارش تازه» for each placed order, if they hold `orders.view`.
  - Tapping opens the order.

**Delivery:**
- A queued `SendPush` job (after commit), TTL 1 h, urgency high.
- On 404/410 the subscription is deleted; 5 failures in a row delete it too.
- Payloads carry no personal data: order number, café name, status and a link.

**Endpoints:**
- `GET /public/push/key` (the public VAPID key);
- `POST /public/orders/{trackedOrder}/push` (`X-Order-Token`);
- `POST` and `DELETE /push/subscription` (staff).

All of them go into the isolation test.

### Web
- `public/sw.js`, served at the root of every host (the proxy passes `/sw.js` through on café subdomains).
  - It handles `push`, which shows the notification (icon, a tag per order so updates replace each other, the URL).
  - It handles `notificationclick`, which focuses an open tab or opens the URL.
- A tracking-page button and a panel toggle (in the notifications bell). Both explain honestly when the browser can't do it: iOS allows push only for a site added to the home screen.

## 2. Self-service signup (`/signup`)
**Step 1** collects:
- café name;
- the address (`{slug}.cafeyar.ir`), suggested from the name, with a live availability check and reserved names blocked;
- owner name, mobile and password.

**Step 2:** the SMS code from the platform line (the existing `OtpService`, now with a scope instead of only a tenant).

**On success:** `CreateTenant` runs (trial status; the Billing listener starts the 14-day Pro trial), the owner is signed in, and the browser goes to the panel with the setup checklist.

**Guards:**
- a config switch, `SELF_SIGNUP` (default on);
- throttles per IP and per phone;
- an OTP proof that is consumed on success;
- a honeypot field;
- an existing user's phone is refused, with a pointer to «ورود» ("sign in").

**API:**
- `GET /public/signup/slug?name=` (suggest and check);
- `POST /public/signup/otp`;
- `POST /public/signup`.

## 3. `/business`: «کافه‌یار برای کسب‌وکارها» ("Cafeyar for businesses")
A rich, animated landing page in our tokens. It works in both themes and on phones.

**Sections:**
- **Hero:** «منو، سفارش و حساب‌وکتاب کافه‌تان در یک جا» ("your café's menu, orders and accounts in one place"), a live-looking phone and panel mock, «شروع رایگان ۱۴ روزه» ("start a free 14-day trial") and «دیدن منوی نمونه» ("see a sample menu").
- **Proof strip:** real platform numbers (cafés on «خوراک‌گردی», cities). Hidden while they are small.
- **Features in 8 groups, each with a mock and 3–4 benefits:**
  1. online menu and landing page;
  2. QR ordering and delivery;
  3. kitchen screen;
  4. customer club, wallet and SMS;
  5. inventory and cost of goods;
  6. staff and payroll;
  7. reports and dashboard;
  8. «خوراک‌گردی» and ads.
- **«چطور شروع کنم»** ("how do I start"): 3 steps.
- **Pricing:** from a new `GET /public/plans`, with prices and feature labels from `FeatureCatalog`/`FeatureLabels`, and a monthly/yearly toggle.
- **FAQ, and a final call to action.**

**Entry points:**
- «کافه یا رستوران دارید؟» in the «خوراک‌گردی» header and footer;
- a band on the explore home;
- the login page («هنوز حساب ندارید؟» ("no account yet?")).

## Tests
- **Push:**
  - an encryption round trip (decrypt with the subscriber's key);
  - the VAPID JWT verifies with the public key;
  - the allow-list;
  - subscribe with and without a valid tracking token;
  - the staff permission check;
  - status changes queue the right pushes;
  - 410 deletes the subscription;
  - isolation.
- **Signup:**
  - slug suggestion and reserved names;
  - OTP wrong, expired and too many attempts;
  - the full signup creates the tenant, owner, branch, trial and subdomain, and returns a working token;
  - existing phone refused, switch off, throttles.
- **Plans:** public, published plans only, no internal fields.
- **Web:** typecheck, lint, build; visual checks of `/business` (390 px and 1280 px, light and dark), `/signup` and the push buttons.

## Risks
- **Push from Iran:** Chrome delivers through Google's FCM. Servers hosted inside Iran may be blocked from `fcm.googleapis.com`, and visitors' Chrome needs FCM too. Firefox (Mozilla) and Safari (Apple) have their own services.
  - The code fails quietly: logged, retried, then dropped.
  - Test on the real host before promising it to cafés. SMS stays the reliable channel.
- **Signup abuse:** OTP plus throttles plus the honeypot; the platform admin can suspend a tenant.
- **Marketing claims:** every feature listed exists in the product today. No invented numbers or customer quotes.
