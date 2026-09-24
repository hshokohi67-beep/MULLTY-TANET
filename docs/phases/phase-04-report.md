# Phase 4 — Payments: Completion Report

**Date:** 2026-09-24 · **Plan:** `phase-04-plan.md` · **Status:** Implemented, awaiting approval

## 1. What was built

### Backend (`app/Modules/Payments`)
| Area | Delivered |
|---|---|
| Data model | `payments` (one row per payment or online attempt), `payment_refunds` (append-only), `payment_transactions` (append-only gateway log). Composite FKs to orders. Unique `(gateway, authority)`, `(tenant, gateway, ref_id)` and `(tenant, idempotency_key)`. `orders` gains `paid_total` / `refunded_total` |
| Order payment status | Derived, never set by hand. `SyncOrderPaymentStatus` recomputes unpaid / pending / partially_paid / paid / partially_refunded / refunded from the payments. `Order::remainingDue()` and `needsRefund()` (money held on a cancelled order, or overpaid) |
| Gateway abstraction | `PaymentGateway` interface (request / startUrl / verify / supportsRefunds). Implementations never throw: they return a `GatewayResult`, and `transient` means "outcome unknown, don't fail it". `GatewayFactory` builds the gateway per tenant at call time |
| Zarinpal v4 | Request → StartPay redirect → verify with the **stored** amount (`currency: IRR`). Codes 100/101 = paid. 5xx, timeouts and non-JSON responses are transient. The merchant ID is sent but never logged. Sandbox is platform config |
| Fake gateway | Local/testing only (refused in other environments). Redirects straight back as paid; an amount ending in 13 rial is declined, to try the failure path |
| Online checkout | `payment_method: online` (web/QR). Requires the tenant setting + merchant ID and a minimum of 10,000 rial. The order is created as `pending_payment` (not on the board, no `OrderPlaced`). `POST /public/orders/{id}/pay` (`X-Order-Token`) opens an attempt; a click within 10 minutes reuses it. A duplicate authority from the gateway fails the attempt safely |
| Verify | `POST /public/payments/{id}/verify {authority}`. The authority must match, and the gateway's `Status` parameter is ignored. A cache lock + row lock make it idempotent: a refresh returns the same result without a second gateway call. On success the order goes `pending_payment → placed` (history, `placed_at` reset, `OrderPlaced` once) |
| Reconcile | `payments:reconcile`, scheduled every minute. Attempts past 15 min are verified once more (catches customers who paid but never came back); if unpaid they expire, and if the gateway is unreachable they are retried, then given up after a day. Orders still unpaid after 30 min are cancelled («پرداخت در مهلت مقرر انجام نشد», "payment not made in time") |
| Discounts | Cancelling or rejecting **any** order now releases its discount usage, so an abandoned payment doesn't burn a coupon |
| Late payments | A payment verified after its order was cancelled is still recorded (the money was taken). The order shows «نیاز به بازگشت وجه» ("refund needed") |
| Staff payments | `POST /orders/{order}/payments`: cash, card reader or other. Amount defaults to the remaining balance and can't exceed it. Idempotent. Refused on cancelled/rejected orders. Paying at the counter releases an order that was waiting for online payment. Audited |
| Refunds | `POST /payments/{payment}/refunds`: up to the refundable amount, checked under a row lock (order → payment). Idempotent. Audited. For online payments the refund is done in the Zarinpal panel and recorded here (§4) |
| Lists | `GET /payments` (method/status/date/branch filters), `GET /orders/{order}/payments`, `GET /payments/summary` (today's takings by method, tenant-local business date) |
| Permissions & settings | `payments.view` / `payments.record` / `payments.refund`. Manager: all three; cashier: view + record. New settings `payments.online.enabled` and `payments.zarinpal.merchant_id` (encrypted, masked, UUID-validated). The settings API returns `meta.payments.test_mode` |
| Dependency direction | Commerce doesn't depend on Payments. Checkout asks the `Commerce\Contracts\OnlinePaymentGate` contract, which the Payments module binds |
| Demo data | Online payment enabled (fake gateway). The counter order is paid in cash and the takeaway order by card reader; the table order is still unpaid |

### Frontend
- **Order page:** a «پرداخت‌ها» ("Payments") card showing paid, refunded and remaining amounts, and each payment's method, status, gateway reference, masked card, POS slip and failure reason. It has a counter-payment form (method, amount prefilled with the remainder, reference) and an inline refund form per payment. A red alert appears when the order needs a refund.
- **Board cards:** «نیاز به بازگشت وجه» ("refund needed") badge.
- **`/dashboard/payments`:** today's takings per method (net of refunds) and a filterable list (method/status chips) linking to the orders.
- **Settings:** «پرداخت اینترنتی (زرین‌پال)» ("Online payment (Zarinpal)") card: enable switch and write-only merchant ID, with a warning when the gateway is in test mode.
- **`/s/{tenant}/pay/{payment}`:** the page the gateway returns to. It verifies server-side and shows success (amount, reference, card, Jalali time), "still pending", or failure with the standard "refunded within 72 hours" note. `noindex` and `no-referrer`, because the URL carries the authority.
- Each form submission carries its own idempotency key; a double click reuses it.

## 2. Verification

| Check | Result |
|---|---|
| `php artisan test` | **246 passed** (1605 assertions). New: online payment (14), staff payments & refunds (8), Zarinpal adapter (3) |
| Isolation harness | **64 endpoint cases**, plus B's order/payment can't be paid or verified through tenant A |
| Larastan level 6 | **0 errors** · Pint: passed |
| Web | `tsc`, ESLint, `next build`: clean |
| End to end (local, fake gateway) | QR cart → online checkout (`pending_payment`) → `/pay` → gateway redirect → result page «پرداخت موفق بود» ("payment successful") with reference and Jalali time → the order appears on the board, paid. Checked in Chrome and over HTTP |
| Dashboard pages (HTTP, logged in) | payments, order detail with payments, settings, orders: 200, Persian text, no `NaN`/`undefined` |

## 3. Security review
- Amounts come only from the database. The verify amount is the stored amount, and the callback's `Status` is ignored.
- The authority is checked with `hash_equals`. The payment ID alone is useless; the order also needs its tracking token.
- Replays are blocked by the payment lock, idempotent paid state and unique authority/ref_id. The same idempotency key used on another order returns 409.
- The merchant ID is encrypted at rest, never returned (masked), and redacted from logs twice: adapters never return it, and `PaymentLog` redacts secret-looking keys.
- The fake gateway is refused outside local/testing.
- The result page URL is kept out of search engines and Referer headers.

## 4. Known gaps / notes
1. **Gateway refunds are recorded, not executed** (plan §7). The Zarinpal refund API needs a separate panel access token we can't test without a live merchant. `supportsRefunds()` is the extension point.
2. **Not yet tried against the real Zarinpal sandbox.** The adapter follows the v4 docs and is covered by HTTP fakes. The first real run needs a merchant ID in settings and `PAYMENTS_DRIVER=zarinpal`.
3. **The customer UI that starts a payment is Phase 7.** The API (`/pay`) and the return page exist now.
4. **Wallet / part-wallet payments** arrive with Phase 5; the model already supports several payments per order.
5. **The scheduler must run in production** (`php artisan schedule:work` or cron) for reconcile/expiry.
6. `#۴` ("#4") renders as "۴#" in RTL text. It's the same across Phase 3 screens; we can switch to «سفارش شماره‌ی ۴» ("order number 4") everywhere in Phase 8 if preferred.

## 5. Try it
```bash
cd apps/api && php artisan migrate:fresh --seed && php artisan serve --port=8765
cd apps/web && npm run dev -- -p 3765     # login 09120000001 / password → «پرداخت‌ها» (Payments), or open an order
php artisan payments:reconcile            # or: php artisan schedule:work
# Real sandbox: set PAYMENTS_DRIVER=zarinpal, ZARINPAL_SANDBOX=true, then enter the merchant ID in «تنظیمات» (Settings)
```
