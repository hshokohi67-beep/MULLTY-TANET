# Phase 4 — Payments: Implementation Plan

**Status:** Implemented (see `phase-04-report.md`) · **Depends on:** Phase 3 (approved 2026-09-24)

## 1. Scope
Take money for orders. Online payment goes through Zarinpal: each cafe uses its own merchant ID, so money settles directly to the cafe. Staff record cash, card-reader (POS) and other manual payments, and refunds. Every gateway interaction is logged. The order's payment status is always derived from its payments.

In scope:
- the `Payments` module;
- `PaymentGateway` interface + Zarinpal v4 adapter + a fake gateway for local/testing;
- online checkout, the callback page and a reconcile/expiry job;
- staff payments and refunds;
- encrypted per-tenant credentials;
- dashboard screens: order payments, the payments list, and payment settings.

Out of scope:
- wallet and part-wallet payments (Phase 5; the model already allows N payments per order);
- IDPay/NextPay adapters (the interface is ready);
- the storefront checkout UI (Phase 7);
- the legacy payment importer (legacy data was test-only, decision D1);
- automatic gateway refunds (see §7).

## 2. Database (tenant-owned, ULID, composite FKs)

| Table | Key columns | Notes |
|---|---|---|
| `payments` | order_id, method (online/cash/card_pos/other), gateway (zarinpal/fake/null), status (pending/paid/failed/expired), amount, refunded_amount, authority, ref_id, card_pan (masked by gateway), fee, idempotency_key, reference (POS slip), note, recorded_by, expires_at, paid_at, failed_at, failure_code | unique(tenant_id, idempotency_key), unique(gateway, authority), unique(tenant_id, gateway, ref_id) |
| `payment_refunds` | payment_id, amount, method (gateway_panel/cash/card/other), reference, reason, actor_id | Append-only |
| `payment_transactions` | payment_id, refund_id?, action (request/verify/reconcile/refund), success, gateway_code, http_status, request JSON, response JSON, duration_ms | Append-only log. The merchant ID and credentials are stripped before storing |

`orders.payment_status` stays on the order but is **recomputed** from payments by one action (`SyncOrderPaymentStatus`):
- net = paid − refunded;
- `refunded` / `partially_refunded` when anything was refunded;
- `paid` when paid ≥ total, `partially_paid` when 0 < paid < total;
- `pending` while an online attempt is open, otherwise `unpaid`.

## 3. Flows
- **Online checkout:** `payment_method=online` (web/QR only).
  - Requires `payments.online.enabled` and a merchant ID. Amount ≥ 10,000 rial.
  - The order is created as `pending_payment`, hidden from the board and KDS.
  - The client then calls `POST /public/orders/{id}/pay` (`X-Order-Token` = the tracking token from checkout) to get the gateway `redirect_url`. This keeps Commerce independent of Payments: checkout only asks the `OnlinePaymentGate` contract whether online payment is available.
  - If the gateway is unreachable, the order stays `pending_payment` and the client retries `/pay`. An open attempt younger than 10 min is reused, so no duplicate authorities are created.
- **Return:**
  - Zarinpal sends the browser (GET) to `{STOREFRONT_URL}/s/{tenant}/pay/{payment}?Authority=…&Status=…`.
  - That Next page calls `POST /public/payments/{payment}/verify {authority}`.
  - The API locks the payment, checks that the authority matches the stored one, and **always verifies server-side** with the *stored* amount. The `Status` query parameter is only logged.
  - Codes 100/101 → paid. Anything else → failed.
  - On paid: the order goes `pending_payment → placed` (history + `OrderPlaced`), and payment_status is recomputed.
  - Re-verifying a paid payment returns the same result (idempotent).
- **Reconcile / expiry:** `payments:reconcile` runs every minute.
  - Pending online attempts older than 15 min are verified once with the gateway (this covers customers who paid but never came back); otherwise they are marked `expired`.
  - Orders still `pending_payment` after 30 min without a paid payment are cancelled («پرداخت انجام نشد», "payment failed").
  - Their discount usage is released. Releasing on cancel/reject now applies to every order.
- **Late payment:** a payment that verifies after its order was cancelled is still recorded as paid (the money was taken). The order shows «نیاز به بازگشت وجه» ("refund needed"): net paid > 0 on a cancelled order, or paid > total.
- **Staff payments:** `POST /orders/{order}/payments {method, amount?, reference?, note?, idempotency_key}`.
  - Method is cash, card_pos or other. The amount defaults to the remaining balance and can't exceed it.
  - Not allowed on cancelled/rejected orders.
- **Refunds:** `POST /payments/{payment}/refunds {amount, method, reference?, reason}`.
  - Amount ≤ amount − refunded_amount, checked under a row lock.
  - For online payments the refund is done in the Zarinpal panel and recorded here with its reference (§7).

## 4. API
- **Public (tenant header):** `POST /public/orders/{trackedOrder}/pay` (`X-Order-Token`), `POST /public/payments/{payment}/verify`.
- **Checkout:** gains `payment_method: cash|online`. An online order comes back as `pending_payment`, and the client calls `/pay`.
- **Dashboard:**
  - `GET /payments` (filters: date range, method, status, branch);
  - `GET /orders/{order}/payments`;
  - `POST /orders/{order}/payments`;
  - `POST /payments/{payment}/refunds`.
  - `OrderResource` gains `payments_summary` (paid, refunded, remaining, needs_refund).
- **Permissions:** `payments.view`, `payments.record`, `payments.refund`.
  - Manager: all three. Cashier: view + record. Owner: all.
- **Settings:** `payments.online.enabled` (bool), `payments.zarinpal.merchant_id` (secret, encrypted, 36-char UUID format).
- **Platform config:** `PAYMENTS_DRIVER=zarinpal|fake` (fake is refused outside local/testing), `ZARINPAL_SANDBOX`, `STOREFRONT_URL`.

## 5. Security
- Amounts always come from the database; the callback never supplies an amount. The verify amount equals the stored amount.
- Verify runs under `lockForUpdate`. The authority must match. Unique `(gateway, authority)` and `(gateway, ref_id)` prevent replays and double-crediting.
- Payment IDs are ULIDs, and verify also requires the gateway authority, which only the payer has.
- Merchant ID is encrypted at rest and never returned (masked). The transaction log strips credentials.
- Staff payments and refunds are idempotent, permission-gated and audited (`audit_logs`).
- Rate limits: `checkout` for pay/verify.
- Isolation harness: every new endpoint, plus a check that tenant B's payment ID can't be verified at tenant A.

## 6. Tests
- **Zarinpal adapter** (HTTP fake): request OK/error, verify 100/101/−51, timeouts, credential stripping.
- **Online checkout:** pending_payment hidden from the board, redirect URL, retry reuses the open attempt, online disabled/min amount errors.
- **Verify:** success → placed + paid + event; replay idempotent; wrong authority; gateway failure → failed and the order stays pending.
- **Reconcile:** paid-late path, expiry + order cancel + discount release.
- **Staff payments:** partial/full, over-remaining refused, idempotent, not on cancelled orders.
- **Refunds:** partial/full, over-refund refused, status recompute.
- Late payment on a cancelled order → needs_refund.
- Settings encryption/masking. Permissions. Isolation.

## 7. Decisions within scope
- **Gateway refunds are recorded, not executed.** Zarinpal refunds use a separate panel access token and API that we can't verify without a live merchant account. V1 records the refund done in the Zarinpal panel (with its reference). The interface has `supportsRefunds()` for adapters that can do it automatically.
- **Pay at counter stays the default.** Online is opt-in per tenant.
- **Amounts are sent to Zarinpal in rial** (`currency: IRR`), matching our storage.

## 8. Risks
| Risk | Mitigation |
|---|---|
| Customer paid but never returned (closed tab, bad network) | Reconcile job verifies open attempts before expiring them |
| Gateway timeouts during verify | The payment stays pending and the reconcile job retries; nothing is marked failed on a network error |
| Double payment (two tabs) | Unique authority/ref_id; the extra payment shows "refund needed" instead of being lost |
| Sandbox/production confusion | Sandbox is platform config, shown in the dashboard settings card |
