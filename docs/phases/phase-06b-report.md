# Phase 6b — Panel Design System & Management Dashboard: Completion Report

**Date:** 2026-09-24 · **Plan:** `phase-06b-plan.md` · **Status:** Implemented, awaiting approval

## 1. What was built

### Visual identity (`packages/ui/src/tokens.css`)
- **"Calm café" palette:**
  - warm paper-like neutrals (`#f6f5f1` background, warm borders) instead of cold greys;
  - a refined teal-green brand (`#0e7c6b`) and a restrained amber accent for highlights;
  - status colours reserved for state.
- **A real dark mode:** its own palette in soft charcoal (never pure black), not an inversion. There is a **light / dark / system switch** in the top bar, applied before first paint (no flash) and remembered per browser. `data-theme` also works on any subtree (the KDS is dark inside a light app).
- Softer layered shadows, larger radii (8–22 px), an ease-out motion curve, and page/drawer/dialog/skeleton animations. Everything is disabled under `prefers-reduced-motion`.
- **Chart tokens** (`--viz-*`) validated with the dataviz validator:
  - light: all hard gates pass; the contrast warning is handled with visible labels and a table view;
  - dark: every check passes.
- `·` separators replaced with `•` across the app, because the middle dot is almost identical to the Persian zero `۰` («۳ عدد · ۲۵۵ هزار», "3 items · 255 thousand", read like an extra digit).

### Component kit (`@cafe/ui`)
- **Icons:** `lucide-react`, bundled and tree-shaken, never from a CDN.
- `Button` with `soft` and `lg` variants and a press feedback; `IconButton` (label required); `buttonClass` helper.
- `Card`/`CardHeader` with an icon slot; `Badge` with status dots and an `accent` tone; `Alert` with icons; `EmptyState` with an icon tile; `Skeleton`.
- `Dialog` (modal and side drawer) on the native `<dialog>`: focus trap, Esc and inert background come from the browser.
- **Charts, hand-written SVG and dependency-free**, following the dataviz method:
  - `Sparkline`;
  - `ColumnChart`: columns plus a neutral reference step line, a legend, hover/focus tooltips, keyboard-focusable slots and a **table view**;
  - `ShareBar`: parts of a whole with 2 px gaps, labelled values and shares;
  - `StatTile`: value, delta against a named period with arrow + sign + words (never colour alone), optional trend.
  - Time flows right-to-left like the page.
- `@cafe/locale`:
  - `formatMoneyCompact` («۲٫۵ میلیون تومان», "2.5 million toman");
  - `formatJalaliLong` now assembles its parts itself, because Chrome rendered «۱۴۰۵ مهر ۲, پنجشنبه» (garbled order); it now gives «پنجشنبه ۲ مهر ۱۴۰۵» ("Thursday 2 Mehr 1405").

### App shell
- A sidebar **grouped with icons**:
  - «عملیات روزانه» ("daily operations"): overview, orders, kitchen, tables;
  - «منو و فروش» ("menu & sales"): menu, discounts, delivery;
  - «مشتریان و مالی» ("customers & finance"): customers, club, payments;
  - «تنظیمات» ("settings"): branches, team, settings.
- The sidebar has an active-item marker and **collapses to an icon rail** (remembered). On phones it is a **slide-in drawer**.
- A frosted sticky top bar with a KDS shortcut, the theme switch and an account menu (switch business, log out).
- The content width grew to 6xl and fades in gently on each navigation.
- **New login page:** form plus a brand panel with the product's highlights. Page headers are restyled.

### Management overview (پیشخوان)
- **API:** `GET /dashboard/overview?range=today|yesterday|7d|30d&branch_id=` (new `Insights` module).
  - Sections are included only with the right permission: sales need `orders.view`, the payment mix `payments.view`, customers `customers.view`.
  - Cached for 90 s, **keyed by the order board's live version** and the minute, so real changes show at once.
- **KPI tiles:** sales, orders, average ticket, cancelled.
  - **Today is compared with yesterday at the same time of day** (not a whole finished day), plus the same weekday last week. Other ranges compare with the previous period.
  - A 14-day sales sparkline sits on the sales tile.
- **Sales chart:** hour by hour against the **average of the last 4 same weekdays** (only active hours are shown), or daily against the previous period for 7/30 days.
- **«همین حالا» ("right now"):** new, preparing and ready counts; late orders (+15 min); table calls; kitchen queue; average prep time today.
- **Best sellers:** quantity plus revenue, with proportional bars.
- **Payment mix:** cash, card reader, online and wallet, net of refunds.
- **Customers:** new members, returning share among member buyers, **birthdays in the next 7 days** (Jalali, "today" badge).
- **Actionable alerts:** late orders, table calls, stuck online payments, orders needing refund, sold-out items, offline KDS tablets, negative wallets. Each links to where it is fixed; they are sorted by severity and permission-filtered.
- Also on the page:
  - range and branch switches;
  - a time-aware greeting;
  - the setup checklist as a foldable progress card (remembered);
  - live refresh through the cheap version check.
- **Orders board:** columns are now tinted panels with a status dot, count and a friendly empty state.

## 2. Verification

| Check | Result |
|---|---|
| `php artisan test` | **327 passed** (3167 assertions). New `OverviewTest` (4): same-time comparisons, the 4-week hourly reference, 7-day series, live counts, payment mix, alerts, Jalali birthdays, permission-filtered sections |
| Isolation harness | the overview endpoint is added |
| Larastan 0 · Pint | passed |
| Web | typecheck, ESLint, build: clean. `@cafe/ui` typecheck clean. `@cafe/locale` tests: 8 pass |
| Palette | dataviz validator: light and dark pass |
| **Visual (Chrome)** | Checked on screen: overview (light and dark), orders, menu, customers, payments, login. Two issues were found and fixed along the way: the dot/zero confusion and the garbled weekday date |

## 3. Notes / next polish
1. **Command palette, global search and settings search** remain in Phase 8, as planned.
2. **Each form screen now uses the new kit automatically.** Deeper per-screen redesigns (e.g. a menu grid with photos, a customer card view) can follow as the owner uses them day to day.
3. The overview is computed live; Phase 11 aggregates will sit behind the same response shape for large tenants.
