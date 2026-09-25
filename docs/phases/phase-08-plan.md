# Phase 8: Dashboard Experience (Plan)

**Status:** implemented (see `phase-08-report.md`)
**Depends on:** Phases 1–7c. The owner asked to start directly after 7c.
**Roadmap scope:** overview with actionable alerts, command palette, global and settings search, onboarding and setup progress, empty states. The overview, alerts, widgets and empty states largely exist since 6b/6c, so this phase adds what is missing.

## 1. Command palette (Ctrl+K / ⌘K, or «/»)
- **Opening:** from anywhere in the panel, and a search button in the top bar (also on phones).
- **One input, grouped results:**
  - **Pages:** every panel page the user may open (permission-aware), with Persian synonyms, e.g. «منو» ("menu") also matches «محصولات» ("products") and «آیتم» ("item").
  - **Settings:** each settings section (brand, pre-order, payments, report, contact…) links to its anchor, e.g. searching «زرین‌پال» (Zarinpal) finds the payments card.
  - **Records** (live from the API, debounced): products, categories, orders (by daily number, contact name or phone), customers (by name or phone).
  - **Quick actions:**
    - «تمام شد / موجود شد» ("sold out / back in stock") on a product, done in place;
    - new product; new story; new discount;
    - open the storefront; switch theme.
- **Keyboard:** ↑/↓ to move, Enter to open, Esc to close; recent picks are remembered on this device. Persian and Latin digits both work, and ی/ي and ک/ك are normalised.
- **A11y:** combobox + listbox pattern with `aria-activedescendant`; works with screen readers.

## 2. Global search API
`GET /dashboard/search?q=`:
- permission-gated per group (`catalog.view`, `orders.view`, `customers.view`);
- up to 5 results per group;
- reuses the existing normalised searches (`Product::search`, `OrderFilters` q, `CustomerDirectory`);
- tenant-scoped and throttled.

## 3. Notification centre
- **Bell in the top bar:** a badge with the count of actionable alerts, and a panel listing them with an icon, text and «بررسی» ("review") link.
- **Data:** `GET /dashboard/alerts` (the same `Alerts` as the overview, branch-aware). The bell reuses the live-version polling (no extra load; the count refreshes only when orders change) and also refreshes when the panel opens.
- **New alert:** «N محصول تمام شده» ("N products are sold out") (availability set to sold out) → menu, filtered. The inventory-based "low stock" alert comes with Phase 9.

## 4. Onboarding & setup progress
- **`GET /dashboard/setup`:** one server-side list of steps with done/not done and a link. The steps:

  | Step | |
  |---|---|
  | menu items | |
  | logo | |
  | cover | new |
  | branch address | |
  | opening hours | |
  | team | |
  | online payment | new |
  | delivery zone | new; if delivery is wanted |
  | table QR codes | new |
  | kitchen station | new |
  | first story | new |

  Each step has a weight, so the essentials come first.
- **Checklist:** a progress ring and "next step" emphasis. The essentials stay pinned until done, optional ones can be skipped (remembered per tenant on the server via a small setting `onboarding.dismissed`).
- **First run:** after a new tenant's first login, a welcome card (the three things to do first) instead of an empty dashboard.

## 5. Tests
- Search: groups by permission, other tenants' records never appear (isolation harness), digits and Arabic letters normalised.
- Alerts endpoint and the sold-out alert.
- Setup steps reflect the data.
- Web gates, and visual checks of the palette, bell and checklist, light and dark.
