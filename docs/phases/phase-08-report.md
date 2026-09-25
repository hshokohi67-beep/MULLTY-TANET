# Phase 8: Dashboard Experience (Report)

**Date:** 2026-09-25
**Plan:** `phase-08-plan.md`
**Status:** Implemented, awaiting approval

## 1. What was built

### Command palette (Ctrl+K / ⌘K / «/», or the search box in the top bar)
- **One keyboard-first list, grouped:**

  | Group | What it holds |
  |---|---|
  | کارها (actions) | new menu item, new story (opens the editor directly), new discount, order history, open the storefront, switch theme |
  | صفحه‌ها (pages) | every page the user may open, with Persian synonyms (e.g. «محصول» ("product") → Menu, «QR» → Tables) |
  | تنظیمات (settings) | each settings card by anchor, e.g. «زرین» ("Zarin") → the Zarinpal payments card, «ظرفیت» ("capacity") → pre-order |
  | Live records | products, categories, orders (daily number, name, phone), customers (name, phone) |

- **Quick action on products:** «تمام شد / موجود شد» ("sold out / back in stock") in place, for all branches, without leaving the page.
- **Input handling:** Persian/Arabic letters and Persian digits are normalised («۲۳» finds order #23). Recent picks are remembered on this device.
- **A11y:** combobox + listbox with `aria-activedescendant`, arrow keys, Enter and Esc. Built on the native `<dialog>`.

### Global search API
- `GET /dashboard/search?q=`:
  - each group only for staff with its permission (`catalog.view`, `orders.view`, `customers.view`);
  - 5 results per group;
  - reuses the existing normalised searches;
  - throttled per user.
- The isolation harness checks that another tenant's products, orders and customers never appear, even for matching terms.

### Notification centre
- **Bell** in the top bar with a count badge (red when something is urgent) and a panel with the actionable alerts and «همه‌چیز مرتب است» ("all good") when there are none.
- **Data:** the new `GET /dashboard/alerts`, the same rules as the overview.
- **Refresh:** every minute while the tab is visible, and always when the bell is opened.
- **New alert:** «N محصول «تمام شد» خورده است» ("N products are marked sold out") links to the menu with a new **«تمام‌شده‌ها» (sold out) filter**. The inventory-based "low stock" alert arrives with Phase 9.

### Onboarding & setup progress
- **`GET /dashboard/setup`:** 11 steps computed from real data. Essentials first (menu, logo, branch address, hours), then online payment, cover, table QR, kitchen station, delivery zone, team and first story.
- **Checklist:** a **progress ring**, «قدم بعدی» ("next step") with an «ادامه» ("continue") button, essentials starred.
- **Skipping:** optional steps can be skipped («لازم ندارم», "I don't need this"), remembered for the café (`onboarding.skipped`, needs `settings.update`); essentials can't be skipped.
- **Links:** the steps and the palette jump straight to the right card (new anchors on the settings cards and the quick-add form).

## 2. Verification

| Check | Result |
|---|---|
| `php artisan test` | **373 passed** (3807 assertions). New `DashboardExperienceTest` (4):<br>• search with Persian words and digits (product, order #۱, phone);<br>• groups follow permissions (the kitchen role gets no customers);<br>• the alerts endpoint and the sold-out alert;<br>• setup steps reflect the data, and skip/restore works (essentials refused, cashier forbidden). |
| Isolation harness | The new endpoints are in the tenant-endpoint matrix, plus `test_global_search_never_crosses_tenants`. |
| Larastan 0 • Pint | passed |
| Web | Typecheck, ESLint, build: clean. `@cafe/ui` tests 6 and `@cafe/locale` tests 8 pass. |
| Visual (your Chrome) | Palette: «لاته» (latte) → both products with the sold-out action; «زرین» → the payments card. Bell with its count; checklist ring. |

**Fixed during the visual pass:**
- The bell stayed on «در حال دریافت…» ("loading…") when the window counted as hidden. An explicit open or the first load now always fetches.
- The ring showed ۷۲٫۷٪; it now rounds.
- The palette input had a double focus box.

## 3. Notes
- In the automated browser the Ctrl+K key didn't reach the page (a limitation of the automation's key dispatch); the search button opens the same palette. Worth one manual try: press Ctrl+K on the dashboard.
- Phase 8 changes are **not committed yet** (only the initial import was, as asked).
