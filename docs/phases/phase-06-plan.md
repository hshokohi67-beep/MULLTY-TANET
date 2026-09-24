# Phase 6 — Kitchen Display (KDS): Implementation Plan

**Status:** Implemented (see `phase-06-report.md`) · **Depends on:** Phase 5 (approved 2026-09-24)

## 1. Scope
Replace the legacy KDS with a module that is multi-station and attributable. The legacy KDS used one shared PIN (`1234` in plaintext), had no stations, and a KDS "ready" completed the order.

In scope:
- kitchen **stations** per branch (e.g. «بار قهوه», "coffee bar", and «آشپزخانه», "kitchen");
- **product → station routing** with a default station;
- **kitchen items** (one per order line) with their own state machine and an append-only **event log**;
- order status driven by the kitchen: first item started → `preparing`; all items ready → `ready`; then optional auto-complete per D9;
- a **board API** with late timers, tier label, customer note, schedule and the waiter-call feed;
- **device pairing**: a tablet is paired with a one-time code and gets its own revocable token (replaces the shared PIN);
- staff can also open the KDS with their own login;
- the **KDS screen** (fullscreen, touch-first, RTL, sound);
- station/device management screens.

Out of scope:
- printing tickets (V2);
- per-item recipes and prep times (Phase 9);
- customer push notifications on "ready" (Notifications phase; events are emitted now).

## 2. Database (tenant-owned, ULID, composite FKs)

| Table | Key columns | Notes |
|---|---|---|
| `kitchen_stations` | branch_id, name, is_default, late_after_minutes (default 7, legacy), is_active, sort | One default per branch (enforced in the action) |
| `kitchen_station_products` | station_id, product_id, branch_id | unique(branch_id, product_id): a product goes to one station per branch |
| `kitchen_items` | order_id, order_item_id (unique), station_id, status (queued/preparing/ready/cancelled), quantity, started_at, ready_at | Created when an order is **placed** (after payment for online orders) |
| `kitchen_events` | order_id, kitchen_item_id?, station_id?, type, actor_type (user/device/system), actor_id | Append-only; replaces the legacy "no attribution" |
| `kitchen_devices` | branch_id, station_id? (null = all stations), name, pairing_code_hash, pairing_expires_at, paired_at, last_seen_at, revoked_at | Sanctum tokenable; tokens are tenant-bound like customer tokens |

## 3. Rules
- **Routing (`RouteOrderToKitchen`, on `OrderPlaced`):**
  - each order line goes to its product's station in that branch, otherwise to the branch's default station;
  - a branch without stations simply has no KDS (orders still work);
  - idempotent (unique order_item_id).
- **Item state machine:** `queued → preparing → ready`, `ready → preparing` (recall), `* → cancelled` (when the order is cancelled or rejected). Every change writes a `kitchen_events` row.
- **Order sync:**
  - the first item started moves `placed/accepted → preparing`, through `TransitionOrder`;
  - when every non-cancelled item is ready, the order goes `→ ready`.
- **D9 auto-complete:** after `ready`, settings `kds.auto_complete_dine_in` (qr_table/dine_in/counter, default on) and `kds.auto_complete_takeaway` (takeaway/phone, default off) complete the order. Delivery is never auto-completed (it needs out-for-delivery). Completion fires `OrderCompleted`, which triggers club rewards.
- **Bump:** marks all of an order's items at one station ready in one action.
- **Pairing:**
  - a manager (`kds.manage`) creates a device and gets a **6-digit code** valid for 10 minutes, stored as a hash and single-use;
  - the tablet enters the code at `/kds/pair` → `POST /public/kds/pair` → a device token (ability `kds`);
  - 5 wrong codes per IP per 15 minutes are rate-limited;
  - revoking a device kills its token immediately.
- **Late:** an item/order is late when now − placed_at > station.late_after_minutes (computed on the client from server timestamps; the server returns `server_time` to correct the tablet's clock).

## 4. API
- **Public:** `POST /public/kds/pair {code}` (tenant header) → `{token, device, station}`.
- **KDS actor** (a device token, **or** a staff token with `kds.operate`):
  - `GET /kds/board?station_id=` returns open orders at the station (queued/preparing items, plus orders that became ready in the last 2 minutes), the waiter-call feed, `server_time` and an `ETag`; `If-None-Match` → **304**;
  - `POST /kds/items/{item}/start|ready|recall`;
  - `POST /kds/orders/{order}/bump {station_id}`;
  - `POST /kds/table-requests/{id}/acknowledge`;
  - `GET /kds/me` (device or staff info + its stations).
- **Dashboard (`kds.manage`):**
  - stations CRUD;
  - `PUT /kds/stations/{station}/products` (routing);
  - devices: list, create (returns a pairing code once), re-pair (new code), revoke.
- **Permissions:** `kds.operate` (kitchen, cashier, waiter, manager), `kds.manage` (manager). Owner gets everything.
- **Settings:** `kds.auto_complete_dine_in`, `kds.auto_complete_takeaway`.

## 5. Realtime
- Every board change dispatches `KitchenBoardChanged(tenantId, branchId)`, a broadcast event on the private channel `tenant.{id}.kds.{branch}`. It carries **no data**, only "refetch", so a leaked channel reveals nothing.
- **Reverb is not installed** in this phase: Packagist is unreachable from this machine (SSL timeout).
- The KDS screen uses **polling with ETag** (every 3 s; unchanged boards cost a 304 with no body). The Reverb client can be switched on later without server changes.

## 6. Security
- No shared PIN: devices use individual, revocable, tenant-bound tokens; staff use their own accounts.
- Every kitchen action records the actor in `kitchen_events`.
- A device token can only reach `/kds/*`, never the dashboard API. A staff token needs `kds.operate`. Items and orders must belong to the device's branch (and station, if the device is bound to one).
- Isolation harness: every new endpoint, plus B's device token being useless at A.

## 7. Tests
- Routing (mapping, default station, no station, idempotent, online orders routed only after payment).
- Item lifecycle and order sync (preparing/ready).
- D9 auto-complete per type (and rewards firing).
- Cancel → items cancelled; recall; bump.
- Board (station filter, recent-ready window, tier label, waiter calls, ETag/304).
- Pairing (TTL, single use, hash only, rate limit, revoke, branch/station scope, no dashboard access).
- Permissions, isolation, broadcast dispatched.

## 8. Risks
| Risk | Mitigation |
|---|---|
| Tablet reliability / reconnects | Stateless polling with ETag; the board is fully recomputed on each fetch; `server_time` for clock skew |
| Realtime package unavailable | Broadcast-ready events + polling now; Reverb is a config change later |
| Kitchen and front-of-house changing the same order | All order changes go through `TransitionOrder` under a row lock; KDS sync only moves forward |
