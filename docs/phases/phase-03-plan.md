# Phase 3 — Commerce: Implementation Plan

**Status:** Implemented (see `phase-03-report.md`) · **Depends on:** Phase 2 (approved 2026-09-24)

## 1. Scope
Everything between "the customer picked items" and "the kitchen has an order", except taking money (Phase 4):
tables + secure QR + table sessions + table requests (call waiter / request bill), customer addresses, radius delivery zones (polygon-ready) with the eligibility pipeline, carts, the order-pricing engine (modifier rules, availability, opening hours / pre-orders), a discount engine V1, checkout with idempotency, orders with an order-status state machine separate from payment status, and dashboard screens (orders board, tables/QR, delivery zones, discounts).

Delivered in this order (each step is tested before the next): **3a** tables/QR/sessions · **3b** addresses + delivery zones · **3c** discount engine · **3d** cart → pricing → checkout → orders + state machine · **3e** dashboard UI.

Out of scope: payment capture (Phase 4; Phase 3 orders are `unpaid` with a pay-at-counter/cash intent), customer storefront UI and E2E journeys (Phase 7), KDS (Phase 6), reservations (V2), courier/GPS (V2), campaigns/campaign_targets/campaign_items (marketing; deferred to Notifications, see §7).

## 2. Database (tenant-owned, ULID, composite FKs)

| Table | Key columns | Notes |
|---|---|---|
| `restaurant_tables` | branch_id, label, capacity, is_active, sort | unique(branch_id, label) |
| `table_qr_codes` | table_id, `token_hash` (sha256), token_hint (last 4), is_active, revoked_at | **Only the hash is stored.** The raw 128-bit token exists only in the printed QR. Rotation revokes old codes |
| `order_sessions` | branch_id, table_id, `session_token_hash`, status (open/closed), opened_at, last_activity_at, closed_at | Several customers at one table share a session; each still gets their own cart and order |
| `table_session_requests` | order_session_id, table_id, type (call_waiter/request_bill), status (open/acknowledged), acknowledged_by | 60s cooldown per table + type |
| `customer_addresses` | customer_id, title, recipient_name, recipient_phone_e164, province, city, district, address, postal_code, building_number, floor, unit, latitude, longitude, notes, is_default, soft deletes | Iranian address shape |
| `delivery_zones` | branch_id, name, type (radius/polygon), radius_m, polygon JSON (future), delivery_fee, free_delivery_min, min_order, eta_minutes, is_active, sort | Amounts in rial |
| `carts` | branch_id, customer_id?, order_session_id?, `cart_token_hash`, order_type, status (active/converted/abandoned), expires_at | Guest carts allowed; claimed by the customer at checkout |
| `cart_items` | cart_id, product_id, variant_id, quantity, modifier_ids JSON, note | Prices are **recomputed on every read**, never stored in the cart |
| `orders` | branch_id, `daily_number`, customer_id?, table_id?, order_session_id?, type, source, status, payment_status, scheduled_for, customer_note, contact_name, contact_phone_e164, address_snapshot JSON, delivery_zone_id, subtotal, discount_total, delivery_fee, total, discount_snapshot JSON, idempotency_key, `tracking_token_hash`, placed_at, accepted_at, completed_at, cancelled_at, cancel_reason | unique(tenant_id, idempotency_key) |
| `order_items` | order_id, product_id, variant_id, product_name, variant_name, unit_price, modifiers_total, quantity, line_total, note | Snapshots: menu changes never alter past orders |
| `order_item_modifiers` | order_item_id, modifier_id, group_name, name, price_delta | Snapshots |
| `order_status_history` | order_id, from_status, to_status, actor_type, actor_id, note | Append-only |
| `order_counters` | branch_id, date (tenant-local), last_number | Daily per-branch order numbers ("order #23"), taken under a row lock. The one extra table, justified by cafe operations |
| `discounts` | name, code (nullable; unique per tenant, case-insensitive), kind (percent/fixed), value, applies_to (order/items), min_order, max_discount, starts_at, ends_at, schedule JSON (weekdays + time window), usage_limit, per_customer_limit, is_automatic, is_active, priority | |
| `discount_rules` | discount_id, rule_type (product/category/branch/order_type/customer), target | Rules of the same type are OR'd; different types are AND'd |
| `discount_usages` | discount_id, order_id, customer_id, amount | Counted for usage limits |

## 3. State machines
- **Order status:** `placed → accepted → preparing → ready → (out_for_delivery →) completed`; `placed|accepted → rejected/cancelled`; `scheduled` orders start as `placed` with `scheduled_for`. `pending_payment` is reserved for Phase 4 online payments. Every transition is validated by `OrderStatusMachine` and written to history. Each transition fires domain events (`OrderPlaced`, `OrderStatusChanged`, `OrderCompleted`) for Phase 5 loyalty and Phase 6 KDS.
- **Payment status** (separate): `unpaid → pending → paid / partially_paid → refunded`. Phase 3 only sets `unpaid`.

## 4. Pricing engine (`OrderPricer`) — the same code for cart preview and checkout
1. Resolve the branch and check it is active; opening hours via `OpeningHoursEvaluator` (closed + `orders.allow_preorder_when_closed` → a `scheduled_for` is required and must fall in the future during an opening interval).
2. For each line: product active, not deleted, **available at this branch** (not sold out/hidden); the variant belongs to the product; unit price = `PriceResolver` for the branch.
3. Modifiers: each belongs to a group attached to the product, is active, and every group's min/max is respected (required groups enforced). Line total = (unit + Σ deltas) × qty.
4. Discount: `DiscountEngine` picks the coupon (if valid) or else the best automatic discount; no stacking in V1; the discount is capped at `max_discount` and never exceeds the eligible amount.
5. Delivery (type = delivery): the address must belong to the customer and have coordinates; the zone is the smallest active radius zone containing the address (haversine from branch coordinates); order minimum; free delivery above the threshold.
6. Total = subtotal − discount + delivery fee, as integer rial end to end.

## 5. API
- Public (tenant via header): `GET /public/tables/{token}` (resolve a QR code to branch + table, or 404), `POST /public/tables/{token}/session` (join/open a session → session token), `POST /public/table-sessions/requests` (call waiter / request bill); carts `POST /public/carts`, `GET/PUT/DELETE /public/carts/{cartToken}/items…`, `GET /public/carts/{token}/quote` (pricing preview incl. discount + delivery); `POST /public/checkout` (**Idempotency-Key** header required); `GET /public/orders/{id}?token=` (tracking by tracking token).
- Customer (customer token): `GET/POST/PUT/DELETE /customer/addresses`, `GET /customer/orders`.
- Dashboard (staff + permissions): tables CRUD + `POST /tables/{id}/qr` (issue/rotate, returns the raw token **once**), open requests + acknowledge; delivery zones CRUD + `POST /delivery-zones/check` (test an address/point); discounts CRUD; orders list/filter + `GET /orders/{id}` + `POST /orders/{id}/status` + `POST /orders` (counter/phone orders by staff).
- Permissions: `orders.view`, `orders.manage`, `orders.create`, `tables.manage`, `delivery.manage`, `discounts.manage`.

## 6. Security
- QR/session/cart/tracking tokens: 32 random bytes, only the SHA-256 is stored, constant-time lookup by hash, rotatable. This replaces legacy's guessable `?table_id=N`.
- Every price is computed server-side at checkout. Client prices are ignored. Checkout runs in a transaction with row locks on counters and discount usage.
- Idempotency key required; replays return the original order.
- Addresses and orders are scoped by customer token + tenant; staff by membership + permission. The isolation harness is extended to every new endpoint.
- Rate limits: carts/checkout/table requests per IP and per token.

## 7. Decisions taken within the approved plan
- **Guest ordering:** allowed for QR table orders (the customer is at the table); delivery and takeaway require a logged-in customer (the phone number is needed).
- **Discount stacking:** none in V1 (a coupon or the best automatic discount). Documented as an extension point.
- **Campaign tables** (`campaigns`, `campaign_targets`, `campaign_items`) are deferred to the Notifications/marketing phase. They are about messaging and targeting, not order pricing. `discounts` + `discount_rules` cover every V1 discount type.

## 8. Tests
Unit: haversine + zone choice, discount engine (percent/fixed/min order/time window/weekday/coupon/limits/cap), state machine. Feature: QR resolve/rotate/revoke, sessions shared by two devices with separate carts, waiter-call cooldown, addresses CRUD + ownership, zones + eligibility errors, cart add/update/remove with modifier rule errors, sold-out rejection, closed branch/pre-order, checkout (dine-in QR guest, delivery with fee/free threshold/min order, coupon), idempotent replay, daily numbering, snapshots unaffected by later menu changes, status transitions + history + invalid transitions, permissions, isolation.

## 9. Risks
| Risk | Mitigation |
|---|---|
| Scope size | Delivered in five tested steps (3a–3e) |
| Price/discount drift between preview and checkout | One `OrderPricer` for both; checkout recomputes inside the transaction |
| Concurrency (order numbers, coupon limits) | Row locks on `order_counters` and on the discount row during checkout |
