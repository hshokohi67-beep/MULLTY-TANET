# Phase 6c — Customisable Dashboard & New Widgets: Implementation Plan

**Status:** Implemented (see `phase-06c-report.md`) · **Depends on:** Phase 6b (approved 2026-09-24) · **Requested by:** the owner, after a research round on restaurant/café dashboards (Toast/Square-style KPIs, SaaS customisation practice).

## 1. Customisation model
- **Widget catalog in code** (`WidgetCatalog`): key, Persian title and description, required permission, allowed sizes (`sm` = 1 column, `md` = 2, `lg` = full width), default size.
- **Role defaults:** owner/manager, cashier, kitchen and waiter each get a sensible default layout. A widget the user may not see is never offered or rendered.
- **Per-user layout** stored on the server (`dashboard_layouts`: tenant, user, ordered `[{key, size}]`), so it follows the user across devices. A user can:
  - show or hide widgets and add new ones from the catalog;
  - **reorder** them (drag and drop, plus up/down buttons for touch and keyboard);
  - change a widget's size;
  - **reset to default**.
- Alerts and the setup checklist are fixed at the top (not widgets): what needs attention always stays visible.
- The range and branch filters still apply to every widget.

## 2. New widgets (from existing data)
| Key | Content |
|---|---|
| `goal` | Daily and monthly sales goal with progress and the month-end projection (goals are tenant settings) |
| `channel_mix` | Sales and orders by channel (table/QR, dine-in, takeaway, delivery, counter, phone, online) |
| `heatmap` | Weekday × hour sales over the last 4 weeks (one-hue sequential ramp, table view) |
| `tables_now` | Occupied tables now, average sitting time today, dine-in sales per seat |
| `discounts` | Per discount in the range: uses, amount given, revenue of those orders |
| `club_liability` | Wallet balances owed to customers, points outstanding (and their value), cashback issued and redeemed in the range |
| `at_risk` | Regulars (3+ orders) whose absence is more than twice their usual gap and over 14 days; the average repeat interval |
| `kitchen_speed` | Average prep time and late share per station in the range |
| `payment_health` | Online attempts, success rate, failed/expired, orders cancelled unpaid |
| `cancellations` | Cancelled vs rejected, top reasons |
| `branches` | Sales, orders and average per branch (only with 2+ branches) |
| `shift_notes` | Short notes from one shift to the next (post, list, delete own; last 7 days) |

The existing sections become widgets too: `kpis`, `sales_chart`, `live`, `top_products`, `payment_mix`, `customers`.

## 3. End-of-day report
- `insights:daily-report` runs hourly. For tenants with `reports.daily_sms` enabled, at the chosen local hour (default 23), it sends the owner an SMS summary (sales, orders, average, cancellations, compared with last week), **once per day**.
- The same summary is also shown in the panel as the `kpis` tile.

## 4. API
- `GET /dashboard/layout`: the layout (saved or role default) plus the catalog the user may use.
- `PUT /dashboard/layout`: validated keys, sizes and permissions. `DELETE /dashboard/layout` resets to the default.
- `GET /dashboard/widgets/{key}?range&branch_id`: the data of one widget. Permission-checked, and cached on the orders live version (like the overview).
- `GET/POST/DELETE /dashboard/shift-notes`.
- **Settings:**

  | Key | Meaning |
  |---|---|
  | `goals.daily_sales` | daily sales goal (rial) |
  | `goals.monthly_sales` | monthly sales goal (rial) |
  | `reports.daily_sms` | send the end-of-day SMS |
  | `reports.daily_sms_hour` | local hour to send it |

## 5. Tests
- Layout: role defaults, saving, rejection of unknown/forbidden widgets, reset, per-user and per-tenant isolation.
- Each widget's figures (channel mix, heatmap buckets in Tehran time, goal projection, sitting time, discounts, liability, at-risk rule, kitchen by station, payment health, cancellations, branches).
- Shift notes (author-only delete).
- Daily report: once per day, the right hour, off by default.
- Permissions and isolation.
