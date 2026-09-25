# Phase 9: Inventory, Recipes, Cost of Goods and Purchasing (Plan)

**Status:** implemented (see `phase-09-report.md`)
**Depends on:** Phases 2–8 (Phase 8 approved 2026-09-25).
**Roadmap scope:**
- ingredients, one stock location per branch, stock, movements, adjustments, recipes;
- sale-time decrement and `order_item_cost_snapshots`;
- purchasing: suppliers, purchase orders, payments.

**Risk named in discovery:** unit conversions (g / ml / pieces).

## 1. Model (new module `Inventory`, depends on Catalog/Commerce/Core)

### Units
- Each ingredient has a **base unit**: `g`, `ml` or `pcs`. Quantities are stored in base units (`decimal(14,3)`).
- Entry units convert on input: kg → 1000 g, L → 1000 ml, pcs → 1 pcs. An ingredient may also define one **pack** (e.g. «بسته‌ی ۱ لیتری» ("1-litre pack") = 1000 ml) for purchasing.

### Cost
- `avg_cost` is integer **rial per 1000 base units** (per kg / per L / per 1000 pcs). This keeps integers while allowing sub-rial per-gram costs.
- It is a weighted moving average, updated when purchases are received.
- The cost of a quantity is `round(qty × avg_cost / 1000)` rial.

### Tables

| Table | Contents |
|---|---|
| `ingredients` | name, base unit, pack label + size, avg cost, low-stock threshold, active |
| `ingredient_stocks` | ingredient × branch → quantity (one location per branch) |
| `stock_movements` | **append-only ledger**: type (`purchase`, `sale`, `sale_reversal`, `adjustment`, `waste`, `count`), signed quantity, `balance_after`, unit cost, order / order item / purchase order refs, note, actor, unique idempotency key |
| `recipe_items` | variant × ingredient × quantity (base units) |
| `modifier_recipe_items` | modifier × ingredient × quantity; may be **negative** to model swaps (almond milk: +200 ml almond, −200 ml milk) |
| `order_item_costs` | the cost snapshot per order item at sale time (rial) plus a JSON breakdown |
| `suppliers` | name, phone, notes |
| `purchase_orders` | supplier, branch, status (`draft`, `ordered`, `received`, `cancelled`), expected date, totals, `paid_total` |
| `purchase_order_items` | ingredient, quantity (base), unit price (per 1000 base), received quantity |
| `supplier_payments` | PO, amount, method (cash/card/transfer), note, paid_at |

### Rules
- **Every stock change goes through `PostStockMovement`:** lock the stock row, append the movement, update the balance. It is idempotent by key. Stock **may go negative** (a sale is never blocked by the books) and is flagged.
- **Sale-time decrement:**
  - on `OrderPlaced` (online orders after payment, like the kitchen), each item's recipe (variant plus its modifiers, × quantity) is decremented at the order's branch;
  - the item's cost snapshot is written at current average costs;
  - keys `sale:{order_item_id}:{ingredient_id}`.
- **Reversal:** on cancel/reject, `sale_reversal` movements put the stock back (keys `reversal:…`); the snapshot stays for the record.
- **Receiving a PO:** receiving (fully or partly) posts `purchase` movements and updates `avg_cost`.
- **Counts:** a stock count posts a `count` movement for the difference.

## 2. API (permissions `inventory.view`, `inventory.manage`, `purchasing.manage`; added to manager; kitchen gets `inventory.view`)
- **Ingredients:** CRUD. The list shows stock per branch, value and low-stock flags.
- **Ledger:** `GET /inventory/movements`, filterable, cursor-paginated.
- **Adjustments:** `POST /inventory/adjustments` (adjustment / waste with reason) and `POST /inventory/counts` (a stock count for many ingredients).
- **Recipes:**
  - `GET/PUT /catalog/products/{product}/recipe` covers variant and modifier recipes;
  - the response includes the **cost, price, margin and food-cost %** per variant.
- **Purchasing:** suppliers CRUD; purchase orders (create/update draft, mark ordered, receive, cancel); supplier payments.
- **Insights:**
  - the **low-stock alert** «N ماده‌ی اولیه رو به اتمام است» ("N ingredients are running low");
  - widgets **food cost & gross margin** (range) and **stock alerts**, registered in `WidgetCatalog`.

## 3. Panel
- **«انبار» ("stock"):** ingredients with a stock bar per branch, low badges, value, adjust/waste drawer, count mode, and each ingredient's history (the ledger).
- **«خرید» ("purchasing"):** purchase orders (status tabs), create with supplier and lines in purchase units, receive (with price corrections), payments and balance owed; suppliers.
- **Product editor → «دستور پخت» ("recipe"):** ingredient rows per size, modifier effects, and a live cost and margin with a warning when food cost exceeds 35 %.
- **Dashboard widgets and the palette** gain the new pages.

## 4. Tests
- **Units:** conversion.
- **Stock:** ledger (locking, idempotency, balance), negative stock allowed and flagged.
- **Recipes:** recipe and modifier-swap decrement on placement; reversal on cancel; cost snapshot.
- **Purchasing:** weighted average cost on receive, partial receive, payments and balance.
- **Alerts and widgets:** low-stock alert, food-cost widget.
- **Permissions and isolation:** foreign ingredient/supplier/PO ids, and another tenant's recipe.
