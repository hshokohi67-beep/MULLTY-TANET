# Phase 2 — Catalog (Menu): Completion Report

**Date:** 2026-09-24 · **Plan:** `phase-02-plan.md` · **Status:** Implemented, awaiting approval

## 1. What was built

### Backend (`app/Modules/Catalog`)
| Area | Delivered |
|---|---|
| Data model | 12 tables (see plan §2): nested categories (max 3 levels), products (soft delete), variants (every product ≥1), prices with **base + per-branch override** (unique through a generated `branch_scope` column), append-only price history, images, per-branch availability, reusable modifier groups/modifiers, import mappings. Every child table uses composite `(id, tenant_id)` FKs |
| Pricing | `SetVariantPrice` is the single write path, so **every** change (manual, bulk, import, override removal) is logged with actor and reason. `PriceResolver`: branch override, otherwise base |
| Bulk prices | ±percent (basis points), ±fixed, exact; over the whole menu, categories (with subcategories) or products; base prices or one branch; rounding (e.g. to 1,000 toman); **preview** without writing; apply in one transaction with a shared `batch_id` + audit summary; negative results refuse the entire batch |
| Rules | category cycle and depth guards; category deletion refused while it has children/products; variant names required when there are 2+ sizes; modifier min ≤ max and defaults ≤ max; max 6 images; "sold out until" expires automatically |
| Search | normalised `search_text` (ی/ي, ک/ك, ة, digits, ZWNJ, diacritics); every word must match |
| Slugs | Persian slugs per discovery D6 (`لاته-وانیلی`, "vanilla latte"), unique per tenant; ZWNJ becomes a hyphen in URLs |
| Public menu | `GET /public/menu?branch=` gives an allow-list projection with branch-resolved prices; hidden/inactive/priceless items removed; cached per (tenant, branch, catalog version), and **every catalog write bumps the version**; `Cache-Control` for the CDN |
| Permissions | `catalog.view`, `catalog.manage`, `prices.manage`, `availability.manage`. Cashier/kitchen can mark items sold out but can't edit the menu or prices. Changing variants through `PUT /products/{id}` also requires `prices.manage`. `permissions:sync` now **grants new default permissions to existing tenants' system roles** (additive; custom roles untouched) |
| WooCommerce importer | `php artisan catalog:import-woocommerce {tenant} --connection=legacy_wp --prefix=wp_ [--unit=toman] [--with-images] [--dry-run]`. Categories (nested), simple/variable products, attribute names, regular price (toman→rial; active sales reported, not imported as base price), calories, diet tags, featured flag, upsells → shared «افزودنی‌ها» ("add-ons") modifier group, optional image download. **Idempotent**: re-running updates in place. Reads the source DB only |
| Demo data | A Persian menu for «کافه نمونه» ("sample cafe"): 5 categories, 8 items, sizes, «نوع شیر» ("milk type") and «افزودنی‌ها» ("add-ons") groups |

### Frontend (`/dashboard/menu`)
- **Menu items:** Quick Add (name + toman price + category), Persian search and category filter, branch switcher, one-tap «تمام شد» ("sold out") / «موجود شد» ("back in stock"), badges (featured, inactive, sold out), sizes count, "from" price.
- **Item editor:** details, categories, diet tags, calories/caffeine, active/featured; sizes and prices; per-branch prices (blank = base); modifier groups; images (upload/delete, first image = cover); delete with inline confirmation.
- **Categories:** tree view, add, edit (parent, order, active), delete with a Persian explanation when refused.
- **Modifiers:** reusable groups with a plain-language selection rule («دقیقاً ۱ انتخاب», "exactly 1 choice"), option prices, and defaults.
- **Bulk pricing:** scope, operation, value, rounding, branch → **preview table** (old/new/difference) → apply. Editing any field after a preview hides «اعمال» ("apply"), so only what was previewed can be applied.
- `MoneyField`: type toman in Persian or Latin digits with or without separators; the formatted amount is shown live as a hint.
- Navigation entry «منو» ("Menu") plus a new setup step on the dashboard.

## 2. Verification

| Check | Result |
|---|---|
| `php artisan test` | **157 passed** (694 assertions). New: catalog CRUD/rules/search/images/modifiers/permissions, pricing (branch override, history, bulk preview/apply/rounding/negative/deleted products, permission gate), public menu (hidden/sold-out expiry/cache invalidation/allow-list/two tenants), WooCommerce import (mini WP database: mapping, idempotent re-run, dry run), permission sync |
| Isolation harness | now **39 endpoint cases** + payload-ID checks (foreign category/product/modifier-group/branch IDs rejected) |
| Larastan level 6 | **0 errors** · Pint: passed |
| Web | `tsc`, ESLint, `next build`: clean; `@cafe/locale` tests pass |
| Rendered pages (HTTP, logged in) | all 5 menu screens return 200; visible text contains correct Persian prices (`۶۵٬۰۰۰ تومان`, `از ۸۵٬۰۰۰ تومان`, "from ۸۵٬۰۰۰ toman"), no `NaN`/`undefined`, and no English apart from the file-format names JPG/PNG/WebP |

## 3. Security review
- Isolation: model scope + membership-before-binding + composite FKs + `TenantExists` validation for every foreign ID inside payloads.
- Price integrity: prices only change through one action that always logs, and pricing through the product update endpoint is gated separately.
- Uploads: raster only, content-sniffed, 2 MB, 200–4000 px, random names under `tenants/{tenant}/products/{product}/`.
- Public menu: explicit allow-list (no SKU, no internal flags, no other tenant).
- Importer: CLI-only, read-only source connection, `unserialize` with `allowed_classes: false`, downloaded images type/size-checked.

## 4. Known gaps / notes
1. **No visual browser check this time:** the Chrome extension wasn't connected. Pages were verified over HTTP (status + rendered text) instead.
2. **MySQL-specific behaviour** (the generated `branch_scope` column, composite FKs) was tested on sqlite locally; CI runs the suite on MySQL 8.4 but hasn't run yet (no git remote).
3. **Image thumbnails:** originals only for now; the storefront (Phase 7) will serve them through the Next image optimiser.
4. **Out of scope as planned:** time-windowed menus, branch-specific modifier prices, stock-driven availability (Phase 9).
5. **The importer has never run against the real legacy database** (its MySQL credentials aren't available). It's covered by a WordPress-shaped fixture instead.

## 5. Try it
```bash
cd apps/api && php artisan migrate:fresh --seed && php artisan serve --port=8765
cd apps/web && npm run dev -- -p 3765     # login 09120000001 / password → «منو» (Menu)
# Import the legacy store (set LEGACY_WP_DB_* in apps/api/.env first):
php artisan catalog:import-woocommerce cafe-nemooneh --dry-run
```
