# Phase 11: Analytics and Reports (Report)

Plan: `phase-11-plan.md`. Status: done, awaiting approval.

## 1. What was built

### Module `Analytics` (above Commerce / Payments / Inventory / Operations; Insights reads from it)

- **Aggregates** (`2026_10_03_100000_create_analytics_tables`). All are tenant-owned with ULIDs and a composite FK to branches.
  - `daily_metrics` (branch × business date):
    - orders, sales, discounts, refunds, cancelled, items, buyers;
    - channel mix and payment mix (JSON);
    - cost of goods and costed/total lines;
    - labour, expenses, waste.
  - `hourly_metrics` (branch × date × local hour).
  - `product_metrics` (branch × date × product: quantity, revenue, cost).
  - `metric_dirty_days`. The migration seeds it from existing orders and expenses, so history builds itself on deploy.
- **Idempotent re-aggregation** (`RollupDay`):
  - it clears the dirty mark, then deletes and rebuilds the day for every branch in one transaction;
  - a cache lock prevents two processes rolling up the same day;
  - "what counts as a sale" lives in `SalesRules`, shared with the dashboard.
- **Dirty marks** (`DirtyDays`) come from model events:
  - order saved;
  - payment saved (refunds);
  - item cost saved or deleted;
  - expense saved or deleted (both the old and the new day);
  - attendance saved or deleted (tenant-local day, old and new);
  - waste movement created.
- **Freshness**:
  - on read, `Metrics::refresh` rolls up to 31 dirty days in the range synchronously; the rest is reported as `stale`;
  - `analytics:rollup` runs every 5 minutes;
  - `analytics:backfill {--tenant=} {--days=400} {--now}` rebuilds history;
  - today's labour is taken live, because open shifts keep costing money without an event.
- **Reports** (`reports.view`, new permission for the manager; the owner has everything). All take `from/to/branch_id` (max 400 days).
  - `summary`:
    - totals and a comparison with the previous period or last year;
    - series by day, or by Jalali month above 62 days, aligned with the comparison by offset;
    - channel mix, payment mix, profit and loss, prime cost, cost-of-goods coverage, sales goal.
  - `products`: quantity, revenue, cost and margin (only when every unit had a recipe cost), share, ABC class (A = first 80% of revenue, B = next 15%, C = the rest); per category.
  - `hours`: weekday × hour, averaged by how many times each weekday occurs in the range; hourly profile; busiest slot.
  - `branches`: full totals per branch and share of sales.
  - `customers` (bounded live query): new, buyers, returning, repeat, share of orders from known customers, average spend, top 10. Phones are masked as `0912***1234`.
  - `inventory`: consumption cost, waste (by ingredient and by reason), purchases received.
  - `export?report=summary|daily|products|branches&format=csv|xlsx`:
    - CSV is UTF-8 with a BOM;
    - XLSX comes from a new dependency-free `App\Support\Export\XlsxWriter`: right-to-left sheet, frozen bold header, Tahoma, thousands separators;
    - money is in toman as numbers, dates are Jalali text;
    - rate-limited (`report-export`, 20 per minute).
- **Dashboard**: `Overview` now reads daily totals, KPIs for past periods and top products from the aggregates. Today's same-time-of-day comparisons and the live board stay live. The existing Insights tests passed unchanged.
- **Demo data**: `AnalyticsDemoSeeder` generates about 90 days of history for both branches:
  - a weekly rhythm (Thursday and Friday busiest), daily peaks, and growth over the period;
  - about 3% cancellations and mixed payment methods;
  - recipe costs per item.

  It writes rows directly, with no stock or kitchen side effects, and is deterministic.

### Panel

- **«گزارش‌ها»** ("Reports", `/dashboard/reports`), second item in the navigation:
  - **Period bar**:
    - presets: today, yesterday, this week, last week, this month, last Jalali month, 30 days, 90 days, this Jalali year;
    - a custom range from two Jalali date pickers;
    - branch filter; comparison (previous period / last year / none).
    - All of it lives in the URL.
  - **Tabs**:
    - *Summary and profit*: tiles with deltas and a sparkline; daily or monthly columns against a comparison line; P&L bars with each cost as a percentage of net sales; prime-cost verdict; channel and payment shares; goal; comparison table.
    - *Products*: ABC cards with advice; sortable table (revenue, quantity, margin) with share bars and gross margin; category share.
    - *Day and hour*: busiest and quietest slot; heatmap; sales per hour.
    - *Branches*: comparison table.
    - *Customers*: tiles; top-10 list linking to each customer.
    - *Inventory*: consumption, waste, purchases; top waste items and reasons.
  - **Export menu**: Excel and CSV for four reports (streamed through a route handler; the staff token never reaches the browser), plus a PDF/print view.
- **Print view** (`/print/reports`): A4 layout with the Persian font and live charts, forced to the light theme, page breaks between sections, and a "print / save as PDF" button. The proxy protects `/print/*`.
- **Command palette**: «گزارش ۳۰ روز اخیر» ("last 30 days report"), «پرفروش‌ترین محصولات» ("best-selling products"), and search keywords.

## 2. Verification

- API: 442 tests, 5277 assertions green (`tests/Feature/Analytics/ReportsTest.php`, 4 scenarios):
  1. The rollup matches the live numbers. A cancellation and a refund arriving the next day correct the original day. Rolling up twice gives identical rows. The dashboard reads the same totals.
  2. Comparison and series, month bucketing, ABC classes and categories, hours heatmap, branches. CSV has the BOM and quoted Persian. XLSX is a right-to-left sheet with Jalali text and numeric toman.
  3. Labour, expenses and waste flow into profit. An expense moved to another day leaves the original day. Customer masking and top list; inventory waste and reasons.
  4. Permissions: the cashier is refused and the manager allowed. Validation: the 400-day cap and unknown formats. A backlog of 40 dirty days is `stale` until `analytics:rollup` clears it.
- Isolation harness (186): every report endpoint and the export; a foreign `branch_id` is rejected on every report.
- Larastan level 6: 0 errors. Pint clean.
- Web: typecheck, lint and build green. `@cafe/ui` 6 and `@cafe/locale` 8 tests green.
- Visual check (headless Chrome, with 90 days of demo history): all six tabs and the print view at 1366 px; dark mode at 390 px; no horizontal overflow at 390 px on any tab.
- Fixed during the check:
  - the default range was not a preset (added «۳۰ روز», "30 days");
  - a missing zero-width non-joiner in «شنبه‌ها» ("Saturdays");
  - `•` in masked phones read as a Persian zero (now `***`);
  - spend amounts wrapping.

## 3. Notes

- On first deploy, the migration marks history dirty and the scheduler builds it. For a large import, run `php artisan analytics:backfill --now`.
- Changing a metric's definition means `analytics:backfill` (the rows are derived data and safe to rebuild).
- A PDF is produced by the browser ("Save as PDF"). Server-side PHP PDF libraries shape Persian poorly; the browser keeps the real font and the charts.
- Dev: my headless test token expired during the check, so a fresh 12-hour dev token was issued for the demo owner (scratchpad only).
