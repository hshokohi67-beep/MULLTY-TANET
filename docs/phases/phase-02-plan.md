# Phase 2: Catalog (Menu): Implementation Plan

**Status:** In progress · **Depends on:** Phase 1 (approved 2026-09-24)

## 1. Scope
The full menu model: nested categories, products, variants (sizes), branch-specific prices with history, auditable bulk price operations, reusable modifier groups, images, per-branch availability ("sold out"), nutrition/diet tags, Persian search, a cached public menu API for the storefront, and a WooCommerce catalog importer (discovery decision D1). Dashboard screens: menu list with Quick Add, product editor, categories, modifier groups, bulk pricing with preview.

Out of scope (later phases or V2): time-windowed menus (breakfast), branch-specific modifier prices, image resizing pipeline (Phase 7 uses the Next image optimiser), inventory-driven availability (Phase 9).

## 2. Database (all tenant-owned: ULID keys, `tenant_id`, composite FKs to parents)

| Table | Key columns | Why |
|---|---|---|
| `categories` | parent_id (self, composite FK), name, slug (unique per tenant), description, sort, is_active | Nested menu (max depth 3) |
| `products` | name, slug, description, `search_text` (normalised), is_active, is_featured, sort, `nutrition` JSON, `dietary_tags` JSON, soft deletes | Soft deletes: orders will reference products |
| `product_categories` | product_id, category_id, sort | A product may appear in several categories |
| `product_variants` | product_id, name (null for the single default variant), sku, is_default, is_active, sort | **Every product has ≥1 variant**, so all pricing attaches to variants |
| `product_prices` | variant_id, branch_id (NULL = base price), amount (bigint rial), generated `branch_scope` + unique(variant_id, branch_scope) | Base price + branch overrides; the unique index works despite NULL |
| `price_change_logs` | variant_id, branch_id, old_amount, new_amount, reason (`manual/bulk/import`), batch_id, actor_id | Price history; bulk operations share one batch_id |
| `product_images` | product_id, path, alt, sort, width, height | Up to 6 per product; sort 0 = cover |
| `product_availability` | product_id, branch_id, status (`available/sold_out/hidden`), sold_out_until | A missing row means available |
| `modifier_groups` | name, min_select, max_select (0 = unlimited), sort | Reusable ("شیر", "milk"; "افزودنی‌ها", "add-ons") |
| `modifiers` | group_id, name, price_delta (bigint rial), is_default, is_active, sort | |
| `product_modifier_groups` | product_id, modifier_group_id, sort | |
| `import_mappings` | source, source_type, source_id, target_id | Importer idempotency (re-runnable) |

## 3. API (`X-Tenant`, staff token, permissions)

- Categories: `GET/POST /catalog/categories`, `PUT/DELETE /catalog/categories/{id}` (delete refused while it has children/products)
- Products: `GET /catalog/products?search&category&featured&status`, `POST /catalog/products`, `POST /catalog/products/quick`, `GET/PUT/DELETE /catalog/products/{id}`, `PUT …/variants`, `PUT …/branch-prices`, `PUT …/modifier-groups`, `POST/DELETE …/images`, `PUT …/availability`
- Modifiers: `GET/POST /catalog/modifier-groups`, `PUT/DELETE /catalog/modifier-groups/{id}` (modifiers synced inside)
- Prices: `POST /catalog/prices/bulk` (with `preview: true` it shows the change without saving), `GET /catalog/price-history?variant_id`
- Public: `GET /public/menu?branch=slug`, cached per (tenant, branch, catalog version); prices resolved for the branch; hidden/inactive items excluded

Permissions: `catalog.view`, `catalog.manage`, `prices.manage`, `availability.manage`. Cashier and kitchen get view + availability ("sold out" toggle); manager gets everything.

## 4. Business rules
- Price resolution: branch override, otherwise base. Money is integer rial; the UI types toman.
- Bulk operations: target = products | categories (+subcategories) | all; scope = base or one branch; operation = ±percent (basis points), ±fixed, exact; optional rounding (e.g. to 1,000 toman). The result must be ≥ 0. Runs in one transaction with a logged batch and an audit summary.
- Modifier group: 0 ≤ min ≤ max (max 0 = unlimited); default modifiers count must be ≤ max.
- Search: `search_text` = normalised name + description, and queries are normalised the same way (ی/ي, ک/ك, digits, ZWNJ).

## 5. Security
Isolation harness extended to every catalog endpoint. Foreign IDs in payloads (category_ids, branch_id, modifier_group_ids, variant ids) are validated inside the tenant. The public menu uses an explicit allow-list resource. Images use the same validation as the logo (raster only, content-sniffed, 2 MB, stored under the tenant prefix). The importer runs only from the CLI, reads from a separate DB connection, and never runs in a web request.

## 6. Tests
Unit: price resolution, bulk calculation (rounding, negative guard). Feature: CRUD + validation (Persian), nested depth, quick add, variants/prices, branch overrides, price logs + batch, bulk preview vs apply, modifiers rules, availability, images, search (Arabic ی/ک), public menu (branch prices, hidden items, cache invalidation), permissions, isolation, importer (sqlite fixture with WordPress tables, idempotent re-run).

## 7. Risks
| Risk | Mitigation |
|---|---|
| Unique index with nullable branch_id | Stored generated column `branch_scope`, tested on sqlite and in CI on MySQL |
| Stale public menu cache | Version key bumped by every catalog write (model events) |
| Importer data variety | Dry-run mode + report; skips unknown product types with warnings |
