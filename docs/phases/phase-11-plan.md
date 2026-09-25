# Phase 11: Analytics and Reports (Plan)

Roadmap row (discovery §E.2): aggregation tables with queued rollups (daily, hourly, product, customer, inventory, financial); comparisons with base values; branch comparison; Jalali-period reports; Excel/CSV/PDF with Persian fonts. Main risk: data that arrives late (refunds, cancellations, corrections), handled by idempotent re-aggregation.

## 1. Approach

- **New module `Analytics`.** It sits above Commerce, Payments, Inventory and Operations and reads from them; Insights (the dashboard) reads from it.
- **Aggregates, one row per branch.**
  - `daily_metrics` (branch, business date):
    - orders, sales, discounts, refunds, cancelled, items, buyers;
    - sales by channel (JSON), payments by method (JSON);
    - cost of goods and costed items;
    - labour, expenses, waste;
    - `computed_at`.
  - `hourly_metrics` (branch, date, local hour): orders, sales.
  - `product_metrics` (branch, date, product): name, quantity, revenue, cost.
  - The definitions match the dashboard: "counted" orders exclude cancelled, rejected and pending-payment.
- **Idempotent re-aggregation.**
  - Model events mark `(tenant, business date)` as dirty in `metric_dirty_days`. The events are: order saved, payment saved (refunds), item cost saved, expense saved, attendance saved, stock movement created.
  - `RollupDay` removes the mark first, then deletes and rebuilds that day's rows for every branch inside one transaction. A change during a rollup simply marks the day dirty again.
  - `analytics:rollup` runs every 5 minutes and flushes dirty days.
  - `analytics:backfill {--tenant=} {--days=400}` handles first deploy and legacy imports.
  - On read, reports flush up to 31 dirty days in the requested range synchronously, so what you see is always current. Anything beyond that is marked `stale` and the scheduler catches up.
- **Customer and inventory reports** read indexed live queries (the customer list and the stock ledger), limited to the range.
- **Dashboard.** `Overview` reads daily totals, KPIs (except today's same-time-of-day comparison, which stays live) and top products from the aggregates, behind the same response shape.

## 2. API (`reports.view`, new permission for owner and manager)

All endpoints take `?from=YYYY-MM-DD&to=YYYY-MM-DD&branch_id=` (range capped at 400 days).

| Endpoint | Returns |
|---|---|
| `GET /reports/summary?compare=previous\|last_year\|none` | Totals and comparison with deltas; a daily series (bucketed to Jalali months above 62 days) with the comparison series; channel mix; payment mix; profit and loss (sales, cost of goods, labour, expenses, waste, profit, prime cost); goal for the period (daily goal × days); `stale` flag |
| `GET /reports/products?sort=revenue\|quantity\|margin` | Per product: quantity, revenue, cost, margin, share, ABC class (A = top 80% of revenue, B = the next 15%, C = the rest); per category |
| `GET /reports/hours` | Weekday (Saturday first) × hour heatmap; hourly profile |
| `GET /reports/branches` | Each branch's totals, average order and share |
| `GET /reports/customers` | New customers, buyers, returning, repeat rate, top 10 by spend (name only, masked phone) |
| `GET /reports/inventory` | Consumption cost, waste by ingredient, purchases received, top waste reasons |
| `GET /reports/export?report=summary\|daily\|products\|branches&format=csv\|xlsx` | Streamed file. CSV is UTF-8 with a BOM. XLSX has a right-to-left sheet with Persian headers, Jalali dates as text and numbers as numbers, written by a small `XlsxWriter` (ZipArchive plus SpreadsheetML, no new dependency) |

**PDF** is a print page in the panel (`/dashboard/reports/print`): an A4 `@media print` layout that uses the panel's Persian font and charts, saved through the browser's "Save as PDF". Server-side PHP PDF libraries shape Persian badly; the browser renders it perfectly.

## 3. Web

`/dashboard/reports`:
- **Period bar:** Jalali presets (today, yesterday, this week, last week, this month, last month, the last 3 months, this year) and a custom range from two Jalali date pickers; branch; comparison (previous period / same period last year / none).
- **Tabs:** summary (tiles with deltas, daily chart against comparison, P&L breakdown, channel and payment share, goal), products (sortable table with ABC badges, category share), hours (heatmap), branches, customers, inventory.
- **Export menu:** Excel, CSV, PDF (print).
- **Navigation and palette:** «گزارش‌ها» ("Reports") entry.

## 4. Security

- Every metric table is tenant-owned (`BelongsToTenant`, ULID, composite FK to branches) and every query is tenant-scoped.
- The rollup and backfill commands run each tenant inside `TenantContext::runAs`. Exports re-enter the tenant context inside the stream callback.
- Customer lists show names and masked phones only.
- All new endpoints go into the isolation harness, including a foreign `branch_id` filter.

## 5. Tests

- Rollup equals the live computation: orders, cancellations, pending payment, refunds, channels, payments, items/products, cost of goods, labour, expenses, waste.
- Late data: cancelling or refunding a past day's order and re-reading gives corrected numbers. Running the rollup twice gives the same rows.
- Range and bucketing, comparison periods, ABC classes, hours heatmap, branch comparison.
- Exports: CSV has the BOM and Persian headers; XLSX is a valid zip with a right-to-left sheet.
- Permissions and isolation.

## 6. Risks

- **Heavy backfills:** chunked by tenant and day; the scheduler uses `withoutOverlapping`.
- **SQLite/MySQL differences in grouping:** all bucketing is done in PHP from per-day rows.
- **Dashboard regressions:** the existing Overview tests must stay green without changes.
