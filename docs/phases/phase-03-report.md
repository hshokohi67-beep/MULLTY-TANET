# Phase 3 — Commerce: Completion Report

**Date:** 2026-09-24 · **Plan:** `phase-03-plan.md` · **Status:** Implemented, awaiting approval

## 1. What was built

### Backend
| Area | Delivered |
|---|---|
| Tables & QR (`Commerce`) | Tables per branch; `POST /tables/{id}/qr` issues a 256-bit token and returns it **once**, stores only its SHA-256, and revokes older codes. A forged or revoked code resolves to nothing. `/public/tables/session` joins or opens the shared table session. The session token is `id.hmac`, so no token column exists. Sessions expire after 180 min idle and staff can close them |
| Table requests | Call waiter / request bill with a 60 s cooldown per table and type; staff list and acknowledge (`/table-requests`) |
| Addresses (`Customers`) | Iranian address shape, own-only CRUD, max 10, one default, soft delete; the order stores a snapshot |
| Delivery zones | Radius zones (polygon column reserved), fee / free-delivery threshold / minimum order / ETA; the smallest active zone containing the address wins (haversine from the branch). `POST /delivery-zones/check` tests a point |
| Discounts (`Discounts`) | Percent (basis points) or fixed; order or item scope; rules (product/category/branch/order type/customer, OR within a type, AND across types); min order, cap, validity window, weekday + time window in tenant time, total and per-customer limits. **A coupon, or else the best automatic discount; no stacking.** Used discounts are deactivated, not deleted |
| Carts | Guest or customer carts via the `X-Cart-Token` header (hash stored); lines merge; **prices are recomputed on every read** |
| Pricing (`OrderPricer`) | One engine for preview (lenient: returns issues) and checkout (strict: throws). Checks: branch active, opening hours / pre-order, product/variant active and available at the branch, modifier membership and group min/max, discount, delivery. Integer rial end to end |
| Checkout | `Idempotency-Key` required. A replay returns the original order, a key reused by another customer/session returns 409, and concurrent duplicates fall back through the unique constraint. Guests may order only from a QR table session; takeaway/delivery require customer login. Daily per-branch numbers (`#۲۳`, "#23") are taken under a lock on `order_counters`. Items and modifiers are snapshotted |
| Orders | An order-status state machine (placed → accepted → preparing → ready → [out_for_delivery →] completed; rejected/cancelled) separate from payment status (`unpaid` in this phase). Append-only history; `OrderPlaced` / `OrderStatusChanged` / `OrderCompleted` events (after commit) for loyalty/KDS. Staff counter/phone orders use `POST /orders`. Customers see `/customer/orders`; guests track through `/public/orders/{id}` with the `X-Order-Token` HMAC |
| Permissions | `orders.view`, `orders.manage`, `orders.create`, `tables.manage`, `delivery.manage`, `discounts.manage` (synced into existing system roles) |
| Cross-cutting fixes | `StoresDatesInUtc`: datetimes that carry an offset are now converted to UTC before saving; they were stored as wall time before. `SecretToken` helper. `TenantSettings` accessor |
| Demo data | 6 tables (QR token for «میز ۱», "table 1", in the log), a 3 km zone, «ساعت خوش عصر» ("afternoon happy hour") auto discount, coupon `YALDA`, 3 live orders in different states, one open waiter call |

### Frontend (dashboard)
- **سفارش‌ها (orders):** open-orders board grouped by status, with next-step buttons (accept/prepare/ready/…, reject/cancel) and open table requests with an acknowledge button. Auto-refreshes. The order page shows lines, modifiers, notes, amounts and a Jalali timeline.
- **میزها و QR (tables & QR):** add/edit tables; issue or rotate the QR (rendered client-side, never stored), print a label; close the table session.
- **محدوده‌های ارسال (delivery zones):** zones per branch in km and toman.
- **تخفیف‌ها (discounts):** create/edit with a plain Persian summary, Iranian weekday chips, `ClockSelect` time window (no native time inputs).
- **Public QR landing** `/s/{tenant}/t/{token}`: resolves the table. Full storefront ordering UI is Phase 7.

## 2. Verification

| Check | Result |
|---|---|
| `php artisan test` | **216 passed** (1272 assertions). New: tables/QR (7), cart & checkout (13), discounts (8), order lifecycle (5), addresses (5) |
| Isolation harness | now **59 endpoint cases**, plus a test that tenant B's QR/session/cart/order tokens are worthless at tenant A |
| Larastan level 6 | **0 errors** · Pint: passed |
| Web | `tsc`, ESLint, `next build`: clean |
| Rendered pages (HTTP, logged in) | orders, order detail, tables, delivery, discounts, overview and menu all return 200; Persian text and amounts (`۲۳۰٬۰۰۰ تومان`, `میز ۲`, `صدا زدن گارسون`, "call waiter"); no `NaN`/`undefined`/`null`, no stray English |
| Chrome | Connected this time. The QR landing renders RTL with «میز ۱» ("table 1"). Dashboard screens weren't checked visually because the browser session needs a password login, which I don't type |

## 3. Security review
- Every storefront secret (QR, cart, table session, order tracking) travels in a header or path, never a query string. Each is stored only as a SHA-256 hash or derived by HMAC; the legacy guessable `?table_id=N` is gone.
- All prices, discounts and delivery fees are computed server-side at checkout inside a transaction. Client amounts are ignored.
- Idempotency is bound to the caller (customer or session), so a reused key can't return another person's order.
- Rate limits: `storefront`, `checkout`, `table-requests`.
- Addresses and orders are owner-only; the isolation harness covers every new endpoint.

## 4. Known gaps / notes
1. **Payments are Phase 4.** All orders are `unpaid` (pay at counter). `pending_payment` is reserved.
2. **Campaign tables deferred** to the notifications/marketing phase (plan §7).
3. **Polygon zones:** the column exists; only radius zones are evaluated.
4. **Board refresh is polling** (AutoRefresh). Push/KDS arrives in Phase 6.
5. As before, MySQL-specific paths were tested on sqlite locally; CI (MySQL 8.4) hasn't run yet because there is no git remote.

## 5. Try it
```bash
cd apps/api && php artisan migrate:fresh --seed && php artisan serve --port=8765
cd apps/web && npm run dev -- -p 3765      # login 09120000001 / password → «سفارش‌ها» (Orders)
# QR landing: take the token from storage/logs/laravel.log ("[demo] QR token for میز ۱"):
# http://127.0.0.1:3765/s/cafe-nemooneh/t/<token>
```
