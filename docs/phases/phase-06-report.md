# Phase 6 — Kitchen Display (KDS): Completion Report

**Date:** 2026-09-24 · **Plan:** `phase-06-plan.md` · **Status:** Implemented, awaiting approval

## 1. What was built

### Backend (`app/Modules/Kitchen`)
| Area | Delivered |
|---|---|
| Stations | Per branch, with a late threshold (legacy default 7 minutes) and active flag. Exactly one default per branch (the first station becomes default, and changing the default moves it). A station that has handled items can't be deleted, only deactivated (history keeps its station) |
| Routing | `kitchen_station_products`: one station per product per branch. Unrouted products go to the default station. `RouteOrderToKitchen` runs on `OrderPlaced`, so online orders reach the kitchen **only after payment**. Idempotent. A branch without stations has no KDS and ordering is unaffected. A kitchen failure never breaks ordering (reported, not thrown) |
| Kitchen items | One per order line: `queued → preparing → ready`, recall `ready → preparing`, `cancelled`. Recall is refused once the whole order is ready (handed over) |
| Order sync | The first item started moves the order `placed → accepted → preparing`. All items ready → `ready`. **D9:** auto-complete per tenant settings (dine-in/QR on by default, takeaway/phone off by default, delivery never). Every step goes through `TransitionOrder` (history, events), so club rewards fire on completion as usual |
| Front of house | Cancelling or rejecting an order cancels its kitchen items. Finishing an order by hand (ready/completed) marks remaining items ready, so the board clears |
| Event log | `kitchen_events` (append-only): routed/started/ready/recalled/bumped/cancelled/closed_by_front, **with the actor** (device, user or system). The legacy KDS had no attribution |
| Board API | `GET /kds/board`: open items plus orders that finished or were cancelled in the last 2 minutes (they leave visibly instead of vanishing). Includes table, customer, **tier label**, note, pre-order time, modifiers, item notes, the waiter-call feed and `server_time` (tablet clock skew). Scheduled orders sort by due time, otherwise FIFO. **ETag / 304** for cheap polling |
| Actions | Start, ready, recall per item; **bump** (all of an order's items at a station); acknowledge a table call |
| Device pairing | Replaces the shared plaintext PIN (`1234`): the manager creates a device and gets a **6-digit code** (10-minute TTL, single use, stored only as an HMAC). The tablet exchanges it for its own token (ability `kds`). The token is tenant-bound (generalised `TenantBoundTokenable`, shared with customers), bound to the device's branch and optionally one station, and revocable instantly. Re-pairing issues a new code and kills the old token. Wrong codes: 10 per 15 minutes per IP |
| Staff access | Staff can open the KDS with their own login (`kds.operate`). A device token can reach only `/kds/*`, never the dashboard or customer APIs |
| Realtime | `KitchenBoardChanged` is a broadcast event on the private channel `tenant.{id}.kds.{branch}`, with channel authorisation for devices and staff. It carries no order data, only "refetch". **Reverb is not installed** (Packagist is unreachable from this machine), so screens poll every 3 s with ETag; switching Reverb on later needs no server code change |
| Permissions & settings | `kds.operate` (kitchen, cashier, waiter, manager), `kds.manage` (manager). Settings `kds.auto_complete_dine_in` and `kds.auto_complete_takeaway` |
| Also | Waiter calls can be acknowledged by a device (no user). Product list accepts `per_page` (max 200) for the routing picker |
| Demo data | «بار قهوه» ("coffee bar", default) and «آشپزخانه» ("kitchen", cakes and breakfast routed there). The open demo orders are on the board in matching states |

### Frontend
- **`/kds`, the kitchen screen:**
  - fullscreen, dark by default (less glare, calm for a whole shift), touch-first;
  - one tap moves an item forward (start → ready), with a small recall button on ready items and a big «همه آماده شد» ("all ready") per card;
  - the card's top edge colour shows urgency: green, amber at 60% of the station's late threshold, then red with a slow "breathing" glow; the timer is corrected for clock skew;
  - also shown: the tier label, notes highlighted, a pre-order badge with its due time;
  - a waiter-call strip with a short nudge animation and a «دیدم» ("seen") button;
  - station tabs and branch picker for staff, open/late counters, clock, connection dot;
  - a generated two-note chime (no audio files), with the sound toggle doubling as the tablet audio unlock;
  - fullscreen button, and a calm empty state;
  - animations respect `prefers-reduced-motion`.
- **`/kds/pair`:** big 6-digit code entry that accepts Persian digits; the tenant comes from the link/QR.
- **آشپزخانه (dashboard):**
  - stations per branch (add, edit, default, late minutes, deactivate, delete) and a product routing checklist per station, showing where each item currently goes;
  - devices: create → big code + QR + link for the tablet; «کد تازه» ("new code") and «لغو» ("revoke"); status (waiting/connected/revoked) and last seen;
  - the D9 auto-complete settings;
  - «باز کردن نمایشگر» ("open display").
- **Tokens:** `data-theme="dark"` now works on any subtree, so the KDS can be dark inside a light app.

## 2. Verification

| Check | Result |
|---|---|
| `php artisan test` | **318 passed** (3047 assertions). New: kitchen flow (9) and devices/pairing/permissions (6); some existing tests grew |
| Isolation harness | **99 tests**: every new endpoint, plus B's tablet token rejected at A (401) and A's staff refused at B's KDS (403) |
| Larastan level 6 | **0 errors** · Pint: passed |
| Web | `tsc`, ESLint (incl. React hooks rules), `next build`: clean |
| Rendered pages (HTTP, logged in) | `/dashboard/kitchen`, `/kds`, `/kds/pair`: 200 in Persian. `/kds/board` returns the demo orders routed correctly (latte + cheesecake split between bar and kitchen) with an ETag |

## 3. Security review
- No shared secret: per-device tokens, revocable and tenant/branch/station-bound. Pairing codes are HMAC-stored, short-lived, single-use and rate-limited.
- Every kitchen action is attributed. Record IDs are checked against the actor's branch/station (a foreign ID looks like 404).
- The broadcast payload carries no data. The KDS tokens stay in HttpOnly cookies on the Next server.

## 4. Known gaps / notes
1. **Reverb (true push)** needs `composer require laravel/reverb` once Packagist is reachable, plus the Echo client. Until then polling (3 s, 304 when unchanged) is in place.
2. **Ticket printing** and **per-item prep times** are V2 / Phase 9.
3. **Customer "your order is ready" notifications** belong to the Notifications phase (the events are emitted).
4. **Not checked visually in Chrome:** opening the KDS needs either a login password or a pairing code, and I don't type those. Test steps are below.

## 5. Next (user request, 2026-09-24)
Before the storefront, a dedicated **panel design + management dashboard** phase:
- a calmer, more polished visual identity for the seller panel;
- an icon set;
- richer `@cafe/ui` components;
- a real management overview with sales trends, peak hours, best sellers, late orders, payments, new customers, birthdays and actionable alerts.

## 6. Try it
```bash
cd apps/api && php artisan migrate:fresh --seed && php artisan serve --port=8765
cd apps/web && npm run dev -- -p 3765
# Staff: log in (09120000001 / password) → «آشپزخانه» ("kitchen") → «باز کردن نمایشگر» ("open display")
# Tablet: «آشپزخانه» → دستگاه‌ها ("devices") → ساخت کد اتصال ("create pairing code") → open the link/QR on the tablet and enter the code
```

## 7. Addendum (same day, on user request): order history, ready alerts, customer tracking
- **Order history** (`/dashboard/orders/history`, tab next to «سفارش‌های باز», "open orders"):
  - Jalali date range with presets (today, yesterday, last 7 days, this month, last 30 days) and a three-select Jalali picker (no native date input);
  - filters by status, type and branch; search by order number (Persian digits too), phone fragment or name;
  - summary tiles: sales after discounts, order count, average rounded to whole toman, cancelled share;
  - "older orders" paging.
  - API: `OrderFilters` (shared by the board and history, adds `from`/`to`/`q`/`payment_status`/`completed_within`) and `GET /orders/summary`.
- **"Order ready" alert** on the open-orders board:
  - a toast plus an optional soft chime whenever an order becomes ready;
  - it also covers dine-in/QR orders that were auto-completed straight from the kitchen (`completed_within=3`);
  - the board now refreshes every 10 s.
- **Customer tracking page** `/s/{tenant}/track/{order}#t=…`:
  - a live stepper (placed → accepted → preparing → ready → [out for delivery] → delivered) with the time of each step, items and total; polls every 5 s and stops when finished; shows a cancelled/rejected state;
  - the token lives in the URL fragment, so it never reaches a server log or a Referer header;
  - the payment result page links to it.
- `@cafe/locale`: `jalaliToGregorian`, `gregorianToJalali`, `jalaliDaysInMonth`, `isJalaliLeapYear`, `todayIn`, `addDays` (tested, incl. a round trip over 800 days).
- `lib/api.ts` accepts extra headers.
- **Verification:** 320 API tests, Larastan 0, Pint, web typecheck/lint/build, locale tests all green. The tracking page was checked visually in Chrome.

## 8. Addendum: polling cost
- `LiveVersion` (in `app/Support/Realtime`) keeps a per-view version in the cache. It is bumped after commit from `Order`, `TableSessionRequest` and `KitchenItem` saves and from `KitchenBoardChanged`.
- **Kitchen board:** the ETag is the version plus the minute plus the view, so an unchanged board returns **304 with no database queries**. Before, the whole board was rebuilt just to compute a content hash.
- **Order board:** instead of re-rendering the whole page every 10 s (about 24 KB each time), the page checks `GET /orders/live-version` every 5 s. When nothing changed that is a 0-byte 304, and it calls `router.refresh()` only when something did.
- **Pausing:** polling stops while the page is hidden, on the dashboard, the KDS and the customer tracking page alike.
- **Measured locally:** an unchanged check is a 304 with a 0-byte body on both boards.
- **Still to do:** with Reverb (once Packagist is reachable) polling is replaced by push.
