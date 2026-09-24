# Phase 6b — Panel Design System & Management Dashboard: Implementation Plan

**Status:** Implemented (see `phase-06b-report.md`) · **Depends on:** Phase 6 (approved 2026-09-24) · **Requested by:** the owner (2026-09-24): "the look of the seller panel and a genuinely useful management dashboard matter a lot; smooth, beautiful and not tiring". Inserted before the storefront (Phase 7), which reuses the same design system.

## 1. Goals
1. **A calm, finished visual identity** for the cafe panel:
   - warm neutral surfaces, one confident brand colour and a restrained accent;
   - generous spacing and consistent radii and shadows;
   - subtle motion that respects `prefers-reduced-motion`;
   - a proper dark mode (a light/dark/system switch).
2. **A complete `@cafe/ui` kit**, so every screen looks the same without hand-rolled markup:
   - icons (`lucide-react`, bundled, never from a CDN);
   - Button variants with icons, IconButton, Input group;
   - Tabs / Segmented control;
   - Table (sortable header style, row hover, responsive overflow);
   - Dialog/Modal and Drawer (focus trap, Esc, RTL), Toast system, Dropdown menu, Tooltip;
   - Skeleton, Avatar, StatTile, Progress, EmptyState with icon, Kbd;
   - a charts set (sparkline, bar, area/line, donut-free share bar), built as plain SVG components following the dataviz method (validated palette, one axis, hover tooltips, table fallback).
3. **A new app shell:**
   - a grouped sidebar with icons (Operations · Menu & sales · Customers · Money · Settings), collapsible to an icon rail on desktop and a drawer on mobile;
   - a top bar with branch switcher, quick actions (new order, open KDS), theme switch, user menu and a live "open orders / table calls" indicator;
   - breadcrumbs/page headers with actions.
4. **A real management dashboard (پیشخوان, "overview")**, answering "how is today going and what needs my attention?". Widgets:
   - **today's sales**, orders and average ticket, each against yesterday at the same time and the same weekday last week;
   - **hourly sales today** vs the typical day (the last 4 same weekdays);
   - **live operations:** open orders by stage, late orders, open table calls, kitchen average prep time today;
   - **payment mix** today (cash, card reader, online, wallet) and items **needing refund**;
   - **best sellers** (today / 7 days) and **slow movers**;
   - **customers:** new members today and this week, returning share, **birthdays in the next 7 days** (with gift status);
   - **actionable alerts:** sold-out items, orders stuck in pending payment, offline kitchen devices, missing setup steps, refunds pending, club negative wallets. Each links to where it is fixed;
   - the setup checklist stays for new cafes (collapsible once done);
   - a branch filter, and a date switch (today / yesterday / 7 days / 30 days) for the KPI block.
5. **Apply the new system to every existing screen:** orders board and history, KDS styling tokens, menu, customers, club, payments, kitchen, tables, delivery, discounts, branches, team, settings, login and select-tenant.

## 2. Backend
- `GET /dashboard/overview?branch_id=&range=today|yesterday|7d|30d` (permission `tenant.view`, and the sections are filtered by the caller's permissions):
  - one action computes KPIs from `orders`/`order_items`/`payments`/`kitchen_items`/`customers`/`loyalty` with **indexed business_date ranges**;
  - cached per (tenant, branch, range) for 60 s and invalidated by the `LiveVersion` orders key;
  - a heavy aggregation module (Phase 11) will later replace the live queries behind the same response shape.
- `GET /dashboard/alerts`: the actionable list above, each with `{type, severity, title, count, href}`.
- Tests: KPI maths (comparison windows, time-of-day cut for "same time yesterday", Tehran time), hourly buckets, best sellers, birthdays across the Jalali year end, alert rules, permission filtering, isolation.

## 3. Design decisions
- **Palette:**
  - brand: a deep, calm teal-green (kept, refined);
  - surfaces: warm off-white/stone in light and soft charcoal in dark (not pure black);
  - accent: amber, used only for highlights;
  - status colours reserved for state, with icon + label.
- **Chart palette:** a small categorical set validated with the dataviz validator for light and dark surfaces. A single series uses brand; status stays separate.
- **Typography:** Vazirmatn, slightly larger base size (15px) for dashboards and tabular numbers for figures.
- **Motion:** 150–250 ms ease-out enters; no looping motion except live indicators. Everything is off under reduced motion.
- **Accessibility:**
  - every icon button has a label;
  - focus rings are visible;
  - charts have a table view and are never colour-only;
  - AA contrast in both themes.

## 4. Risks
| Risk | Mitigation |
|---|---|
| Scope creep ("redesign everything") | Kit + shell + overview first, then screens in priority order (orders → menu → customers → the rest) |
| Live KPI queries get slow for big tenants | Indexed date ranges, 60 s cache, same response shape for the Phase 11 aggregates |
| Bundle size | Icons tree-shaken; charts are hand-written SVG (no charting library) |
