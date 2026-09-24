# Phase 7c: Scheduled Pre-orders, Selective Glass and a Professional Cart (Plan)

**Status:** implemented (see `phase-07c-report.md`)
**Requested by:** the owner (2026-09-25). They approved Claude's proposals: complete pre-orders with the proposed defaults, glass only where it helps, and a full cart/checkout review.

## 1. Scheduled pre-orders

### Settings (`TenantSettingsRegistry`)

| Key | Default | Meaning |
|---|---|---|
| `preorder.lead_minutes` | 30 | earliest slot = now + lead |
| `preorder.max_days` | 3 | how many days ahead |
| `preorder.slot_minutes` | 15 | slot length (10/15/20/30/60) |
| `preorder.slot_capacity` | 0 | orders per slot; 0 = unlimited |
| `preorder.release_minutes` | 20 | how long before the slot the order goes to the kitchen |

### API
- **`GET /public/preorder-slots?branch_id=`:** days (tenant-local) with slots, built from the branch's opening hours. Each slot shows start (UTC), local label and whether it is available (capacity). It also says whether "now" is possible (branch open).
- **Pricer:** a scheduled time must be:
  - ≥ now + lead;
  - ≤ the last allowed day;
  - inside opening hours;
  - in a slot with room (checked strictly at checkout, under a lock on the branch's slot count).

  Each failure has its own Persian error code.
- **Kitchen release:**
  - an order whose slot is further away than the release window is **not** routed at placement;
  - `kitchen:release-preorders` (every minute, cross-tenant scan inside `TenantContext::bypass()`, then `runAs` per tenant) routes it when `scheduled_for − release ≤ now`;
  - idempotent.
- **`OrderResource`:** gains `kitchen_release_at`, so the board knows it is still "upcoming".
- **Alert:** «N پیش‌سفارش برای یک ساعت آینده» ("N pre-orders for the next hour").

### Panel
- Settings → «پیش‌سفارش» ("pre-order") card with the five settings plus the existing "accept pre-orders when closed".
- Orders board: a **«پیش‌سفارش‌ها» (pre-orders) column** sorted by pickup time, with a big "تحویل ساعت …" ("pickup at …") label and a countdown to release. Released pre-orders move to «جدید» ("new") automatically on the next refresh.

### Storefront
- **Checkout time picker:** «همین حالا» ("right now", when open) or a pre-order via day tabs (امروز / فردا / weekday+date: today / tomorrow / …) and slot chips. Full slots are shown disabled, and there is no free-form time any more.
- **Tracking:** «زمان‌بندی‌شده برای …» ("scheduled for …") on scheduled orders.

## 2. Selective glass
- **Tokens:** `--glass-bg` and `--glass-border` (light/dark) plus a `.glass` utility (backdrop blur + saturation, a hairline highlight border, a soft shadow).
- **Fallbacks:** a solid surface when `backdrop-filter` is unsupported or `prefers-reduced-transparency: reduce`.
- **Applied to:**
  - the sticky search/category bar;
  - the cart bar and the checkout total bar;
  - the hero pills;
  - the sheet header;
  - the story viewer controls.
- Product cards stay solid.

## 3. Cart & checkout redesign
- **Steps with numbered headings:** your order → delivery → time → payment → note. The summary card gets a clear hierarchy: subtotal, discount, delivery, wallet, payable.
- **Lines:** product illustration or photo thumbnail, modifiers as small chips, the per-line problem inline, a compact stepper.
- **Coupon:** collapsible «کد تخفیف دارید؟» ("have a discount code?") with an applied-state chip and remove.
- **Delivery:** address cards with a map-pin icon and the zone fee/ETA inline; the add-address card.
- **Free-delivery progress:** «۱۲۰ هزار تومان تا ارسال رایگان» ("120 thousand toman to free delivery").
- **Payment:** cards with trust hints; a wallet row with the balance.
- **Phones:** a glass bottom bar with the payable amount and the action.
- **Empty and loading states** matching the layout.

## 4. Tests
- **Slots:** hours, lead, max days, capacity, closed days, overnight hours.
- **Pricer:** rejects too soon, too far, closed, full.
- **Release:** a far pre-order is not routed at placement and is routed by the command at the right minute, with other tenants untouched.
- **Board data:** `kitchen_release_at`; the alert; the settings validation.
- **Isolation:** slots of another tenant's branch.
- **Web gates and visual checks** at 375 px, light and dark.
