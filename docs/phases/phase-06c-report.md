# Phase 6c — Customisable Dashboard & New Widgets: Completion Report

**Date:** 2026-09-24 · **Plan:** `phase-06c-plan.md` · **Status:** Implemented, awaiting approval

## 1. What was built

### Customisation
- **Widget catalog** (`Insights\Support\WidgetCatalog`, 18 widgets): each has a Persian title and description, a required permission, allowed sizes (کوچک، متوسط، پهن — small, medium, wide) and a default size.
- **Role defaults:**
  - owner: sales, goal, chart, live, best sellers, channels, payments, customers, heatmap, club liability, at-risk, notes;
  - manager: an operations-heavy set;
  - cashier: live, KPIs, tables, payments, notes;
  - kitchen: live, kitchen speed, best sellers, notes;
  - waiter: live, tables, notes.
- **A per-user layout stored on the server** (`dashboard_layouts`), so it follows the user across devices.
  - Unknown widgets are rejected. Widgets the user may not see are silently dropped, and duplicates and invalid sizes are corrected on save.
  - Each user's layout is independent.
- **Edit mode («شخصی‌سازی پیشخوان», "customise dashboard"):**
  - **drag and drop** reordering, with up/down arrows for touch and keyboard;
  - a size switch per widget and hide (eye) per widget;
  - an **«افزودن ویجت» ("add widget") drawer** listing hidden widgets with descriptions;
  - «پیش‌فرض» ("default") to reset;
  - cancel. Newly added widgets load their data after saving.
- The alerts and the setup checklist stay pinned above the widgets. The range and branch filters still apply to all widgets.

### New widgets (API: `GET /dashboard/widgets/{key}?range&branch_id`, permission-checked, cached on the live version)
| Widget | What it shows |
|---|---|
| هدف فروش (sales goal) | Today and this month vs goal, the month-end projection at the current pace and days left. Editable inline (`settings.update`) |
| کانال‌های فروش (sales channels) | Sales, share and orders per channel (table/QR, takeaway, counter, …) |
| ساعت‌های شلوغ هفته (busy hours) | Weekday × hour heatmap, Saturday first, of the average weekly sales over 4 weeks. Single-hue ramp, legend, hover/focus values, table view |
| وضعیت میزها (tables) | Occupied tables, average sitting time today, dine-in sales per seat |
| سرعت آشپزخانه (kitchen speed) | Average prep time and late share per station (against each station's threshold) |
| لغوها (cancellations) | Cancelled vs rejected, share of all orders, top reasons |
| مقایسه‌ی شعبه‌ها (branches) | Sales, orders and average per branch |
| سلامت پرداخت آنلاین (online payments) | Gateway success rate, failed, expired, abandoned unpaid orders |
| مشتریان در خطر رفتن (at-risk customers) | Regulars (3+ visits) absent for more than twice their usual gap and at least 14 days, plus the typical repeat interval |
| بدهی باشگاه (club liability) | Wallet balances owed plus the value of outstanding points; cashback/gifts issued and wallet spend in the range |
| عملکرد تخفیف‌ها (discounts) | Uses, amount given and revenue of those orders, per discount |
| یادداشت شیفت (shift notes) | Handover notes for the next shift (last 7 days). Authors delete their own; `team.manage` can moderate |

The existing sections (KPIs, sales chart, «همین حالا» ("right now"), best sellers, payment mix, customers) became widgets too.

### End-of-day report
`insights:daily-report` runs hourly. At the tenant's chosen local hour it sends the owner(s) an SMS: sales, orders, average, cancellations, and the % change vs the same day last week. It is sent **once per day** (cache guard), is off by default, and is configured in «تنظیمات ← گزارش پایان روز» ("settings → end-of-day report").

### Also
- **Bug fixed:** closing or opening a table session didn't bump the live version, so the tables widget could show a stale occupancy for a minute. Sessions now bump it too (found by the widget tests).
- `Heatmap` was added to `@cafe/ui` (with a money wrapper). The drawer slides in from its own edge.
- Settings added: `goals.daily_sales`, `goals.monthly_sales`, `reports.daily_sms`, `reports.daily_sms_hour`.

## 2. Verification

| Check | Result |
|---|---|
| `php artisan test` | **339 passed** (3395 assertions). New `DashboardWidgetsTest` (6): role defaults, save validation and reset, per-user isolation, 403 on forbidden widgets, channel/heatmap (Tehran weekday and hour)/goal/cancellation figures, kitchen speed per station, tables, branches, payment health, at-risk rule, liability, discount performance, notes permissions, daily report hour/once/off |
| Isolation harness | **108 tests**: layout, widget and shift-note endpoints, including another tenant's note |
| Larastan 0 · Pint | passed |
| Web | typecheck, ESLint, build: clean; locale tests pass |
| Visual (Chrome) | Checked on screen: widget grid, heatmap, liability, notes, at-risk; edit mode (controls on every widget), the add-widget drawer, cancel. Fixed after viewing: a single busy hour stretching the heatmap (now at least 8:00–22:00 is always shown), and gaps in the grid (dense packing) |

## 3. Notes
- Widgets that need later phases (food cost and gross margin, labour cost, prime cost, menu profitability matrix, sales forecast, ratings) are added in their phases (9, 10, 11). The catalog is the single place to register them.
- The heatmap and at-risk scans read up to a year of orders live; the Phase 11 aggregates will take over for large tenants behind the same API shape.
