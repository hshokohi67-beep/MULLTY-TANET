# Phase 9: Inventory, Recipes, Cost of Goods and Purchasing (Report)

**Date:** 2026-09-25
**Plan:** `phase-09-plan.md`
**Status:** Implemented, awaiting approval

## 1. What was built

### Module `Inventory` (depends on Catalog / Commerce / Core)

**Units and cost**
- Stock is kept in each ingredient's base unit (g, ml, pcs).
- Staff type what they buy: kg, L, pieces, or the ingredient's **pack** (e.g. «پاکت ۱ لیتری», "1-litre carton" = 1000 ml). `Units` converts both quantities and prices.
- Cost is integer **rial per 1000 base units** (per kg / L / 1000 pcs), so per-gram costs stay exact without floats.

**The stock ledger**
- `PostStockMovement` is the only way stock changes: it locks the ingredient-branch row, appends a movement with its `balance_after`, and is idempotent by key.
- Movement types: purchase, sale, sale reversal, adjustment, waste, count.
- Stock **may go negative**: a sale is never blocked by the books, and the panel flags it.

**Sale-time decrement and cost snapshot** (`ConsumeOrderStock`, on `OrderPlaced`, the same trigger as the kitchen)
- Every item's recipe comes out of that branch's stock: the size's recipe plus the modifiers' effects, × quantity.
- A modifier can **add or remove**: «شیر بادام» (almond milk) = +200 ml almond milk and −200 ml milk.
- The item's cost is frozen in `order_item_costs` at the current average costs.
- Cancel or reject puts back exactly what the ledger took, so later recipe edits don't matter.

**Purchasing**
- Suppliers.
- Purchase orders: draft → ordered → received (partly or fully) → cancelled. A partly received order can't be cancelled.
- Receiving posts `purchase` movements and updates the **weighted moving average cost**, and the price can be corrected on receipt.
- Supplier payments: overpayment is refused.
- **Debt counts only goods that have arrived**: an order that is only placed is not owed yet.

**Permissions**

| Permission | Covers |
|---|---|
| `inventory.view` | viewing stock |
| `inventory.manage` | ingredients, recipes, waste, counts |
| `purchasing.manage` | suppliers, purchase orders, payments |

The manager role gets all three; the kitchen role gets `inventory.view`.

### Panel
- **«انبار» (stock):**
  - summary tiles: stock value, ingredients, running low, negative stock;
  - search, branch, «فقط رو به اتمام‌ها» (running low only);
  - per ingredient: stock with a coloured bar and a threshold mark, value, average cost per kg / L / piece;
  - a **waste / adjustment** dialog: adjustment asks for a reason; +/− direction; any unit;
  - add/edit drawer: base unit fixed after creation, opening price, pack, low-stock level in kg / L / pieces;
  - each ingredient's **history**: the full ledger, linking to the order or purchase.
- **Stock count («انبارگردانی»):** a counting sheet with book quantity next to the input and the difference shown live. Only filled rows are sent; differences post as `count` movements.
- **«خرید» (purchasing):**
  - tabs: open / unpaid / received / all;
  - orders show status and what is still owed;
  - suppliers list with orders and debt; inline add/edit;
  - new/edit draft typed in purchase units, with the last average price shown as a hint and live totals;
  - order page: items (ordered vs received), account (total / paid / due, payments), **«ثبت سفارش» / «تحویل گرفتم» / «لغو»** ("place order / received / cancel"), and the payment form.
- **Product page → «دستور پخت و بهای تمام‌شده»** (recipe and cost):
  - rows per size and live **cost, gross margin and food-cost %** (a warning above 35 %);
  - «اثر افزودنی‌ها روی مواد» ("effect of modifiers on ingredients") with add/remove toggles.
- **Dashboard:**
  - the roadmap's **low-stock alert** «N ماده‌ی اولیه رو به اتمام است» ("N ingredients are running low") appears on the overview and in the bell, linking to the filtered stock page;
  - widgets **«بهای تمام‌شده و سود ناخالص»** (cost of goods and gross margin: revenue, cost, gross margin, food-cost %, most profitable items, a coverage note when some items have no recipe) and **«هشدار موجودی»** (stock alerts).
- **Navigation and palette:** a new «انبار و خرید» (stock and purchasing) group. The palette finds stock and purchasing, and has «ثبت سفارش خرید» (new purchase order) and «انبارگردانی» (stock count) actions.
- **Demo data:** `InventoryDemoSeeder`: seven ingredients, recipes for the coffee drinks with milk swaps, two suppliers, two received orders and one open order.

## 2. Verification

| Check | Result |
|---|---|
| `php artisan test` | **400 passed** (4435 assertions). New `InventoryTest` (6):<br>• recipe cost/margin per size (kg/L entry);<br>• purchase → receive → stock and average cost;<br>• a sale with the almond-milk swap decrements exactly and freezes the cost (2 × 364,600);<br>• cancel reverses once;<br>• weighted average with partial receipt and a price correction;<br>• payments, overpayment and debt;<br>• waste (L → ml), adjustment needs a reason, count differences, ledger order;<br>• low-stock alert and filter;<br>• permissions (cashier none, kitchen view-only), unique names, fixed base unit, unit mismatch, in-use delete refused;<br>• food-cost and stock widgets. |
| Isolation harness | **153 tests**:<br>• every new endpoint in the tenant matrix;<br>• another tenant's ingredient, supplier and purchase order are 404 and untouched;<br>• foreign ingredient/supplier IDs are rejected in adjustments, purchases and recipes. |
| Larastan 0 • Pint | passed |
| Web | Typecheck, ESLint, build: clean. `@cafe/ui` tests 6 and `@cafe/locale` tests 8 pass. |
| Visual | Stock page, the latte's recipe card (food-cost warning shown), purchases with suppliers. Headless at 1280 px (the Chrome extension was disconnected). |

**Found during the visual pass:** an order that was only placed counted as debt. Debt now counts only delivered goods (`owing` scope).

## 3. Notes
- Stock uses **one location per branch**, as the roadmap says; transfers between branches can be added later as a movement pair.
- Average cost is tenant-wide (all branches), weighted by what is on hand.
- The demo prices make the latte's food cost look high (about 55 %) because the demo menu prices are low; the warning is working as intended.
