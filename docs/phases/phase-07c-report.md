# Phase 7c: Scheduled Pre-orders, Selective Glass and a Professional Cart (Report)

**Date:** 2026-09-25
**Plan:** `phase-07c-plan.md`
**Status:** Implemented, awaiting approval

## 1. What was built

### Scheduled pre-orders
- **Settings** (Settings → «پیش‌سفارش», "pre-order"). The existing "accept pre-orders when closed" toggle moved here too.

  | Setting | Default | Choices |
  |---|---|---|
  | Earliest pickup | 30 min | 0–180 |
  | Days ahead | 3 | today only up to 14 |
  | Slot length | 15 min | 10/15/20/30/60 |
  | Slot capacity | unlimited | any number |
  | Kitchen release | 20 min before | 0–90 |

- **`PreorderSchedule` (Commerce):**
  - cuts each branch's opening hours (tenant-local, overnight hours included) into slots from now + lead to the end of the last day;
  - labels days «امروز / فردا / weekday + date» ("today / tomorrow / …") and marks full slots;
  - the pricer uses **the same class** to validate a chosen time, so the picker and the server never disagree;
  - new Persian errors `schedule_too_soon`, `schedule_too_far` and `slot_full`;
  - at checkout, capacity is read under a lock on the branch row, so two customers can't take the last place.
- **`GET /public/preorder-slots?branch_id=`:** days, slots, open-now and lead time.
- **Kitchen release:**
  - pre-orders are no longer routed to the kitchen when placed;
  - `kitchen:release-preorders` (scheduled every minute, idempotent) sends each one `release_minutes` before its slot;
  - the cross-tenant scan runs in `TenantContext::bypass()`, then routing runs per tenant with `runAs()`.
- **Orders board (صندوق, cashier):**
  - a separate **«پیش‌سفارش‌ها» (pre-orders) lane** above the columns, sorted by pickup time;
  - each card shows a large «تحویل امروز ۱۸:۳۰» ("pickup today 18:30") badge, with the date when it's not today;
  - once released, the order moves into «جدید» ("new") by itself (live refresh);
  - a pre-order's waiting time counts from its slot, not from when it was placed.
- **Dashboard alert:** «N پیش‌سفارش برای یک ساعت آینده» ("N pre-orders for the next hour").
- **Bug fixed:** the "waiting over 15 minutes" alert counted pre-orders placed long before their slot as late.
- **Customer tracking:** «پیش‌سفارش برای … ؛ آماده‌سازی کمی قبل از این زمان شروع می‌شود» ("pre-order for …; preparation starts a little before this time").

### Glass (only where it helps)
- A `.glass` utility: translucent surface, 18 px blur with saturation, a hairline highlight and a soft shadow. `.glass-light` is for use over dark photos.
- **Fallback:** a solid surface without `backdrop-filter` or under «reduce transparency».
- **Used on:**
  - the sticky search/category bar;
  - the new cart bar: glass with a solid brand «total • ادامه» ("total • continue") button;
  - the checkout bottom bar on phones;
  - the hero pills;
  - the story viewer controls.
- Product cards stay solid, which keeps them readable and scrolling fast on low-end phones.

### Cart & checkout redesign
- **Layout:** «تکمیل سفارش» ("complete your order") with a way back to the menu and the branch/table chip. **Numbered step cards:** your order → how you get it → when → payment.
- **Lines:** an illustration tile, variant/modifier chips, the problem inline, and a compact stepper with delete on the last one.
- **Options:** large selectable cards with a radio indicator.
- **Delivery:** zone, ETA and fee inline, plus a **"X toman to free delivery" progress bar**.
- **Time:** «همین حالا» ("right now") is disabled with «الان بسته‌ایم» ("we're closed now") when closed. Pre-order uses day tabs and a slot grid; full slots are struck through. When the café is closed, the first free slot is pre-selected.
- **Payment:** online/cash cards, a wallet row with a real switch and the balance, and a note field.
- **Summary card:** a collapsible «کد تخفیف دارید؟» ("have a discount code?") that becomes an applied-code chip with remove, a clear total hierarchy, a pre-order reminder chip, and «پرداخت امن از درگاه شاپرک» ("secure payment via the Shaparak gateway").

### Also
- **Your photo on آیس لاته (iced latte):** menu cards now show the **whole photo** on the mood backdrop (it used to be cropped). Only the tall featured tiles still use a cover crop.
- **Brand colour:**
  - a very pale colour (such as the current mint `#bcf5f0`) used to be darkened by mixing with black, which made it muddy grey;
  - it is now darkened in **lightness only, keeping hue and saturation**, and deepened a little more so white text reaches AA;
  - the mint now renders as a clear teal `#14857b`; strong colours are unchanged;
  - covered by a new `@cafe/ui` test.

## 2. Verification

| Check | Result |
|---|---|
| `php artisan test` | **364 passed** (3751 assertions). New `PreorderTest` (5):<br>• slots from hours, lead time and days, and settings changing the grid;<br>• too soon / too far / closed rejected, and `kitchen_release_at`;<br>• capacity shown and enforced;<br>• a pre-order routed at the right minute, idempotently, while an order for now goes straight to the kitchen;<br>• the upcoming alert, and a pre-order not counted as late. |
| Isolation harness | Another tenant's branch is rejected by the slots endpoint. |
| Larastan 0 • Pint | passed |
| Web | Typecheck, ESLint, build: clean. `@cafe/ui` tests 6 and `@cafe/locale` tests 8 pass. |
| Visual | New cart at 375 px (slots, steps, glass bar), the menu with the new glass bars and brand colour, and the settings card, in your Chrome. |

## 3. Notes
- `kitchen:release-preorders` needs the scheduler running (`php artisan schedule:work`, or cron in production), like the other scheduled commands.
- The slot grid shows up to a full day of slots (e.g. 16:00–23:45 on Fridays). If that feels long, it can be grouped by part of day later.
