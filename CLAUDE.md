# Project conventions

Multi-tenant cafe SaaS. Read `docs/discovery/00-discovery-report.md` and the latest `docs/phases/*-report.md` before starting work.

## Workflow
- Reply to the owner in Persian (docs, code comments and commit messages stay in English).
- Work phase by phase. Before a phase: write `docs/phases/phase-NN-plan.md` (files, DB, API, security, tests, risks). After: `phase-NN-report.md`. Wait for approval before starting the next phase.
- A phase is done only when tests, Larastan (level 6, no ignores/baseline), Pint, web typecheck/lint/build are green.

## Backend (apps/api)
- Modules live in `app/Modules/<Module>/` (Models, Actions, Data, Http/{Controllers,Requests,Resources}, Database/Migrations, Providers, routes.php). Cross-cutting code lives in `app/Support/`.
- Controllers are thin. Business logic goes in Actions; input goes through FormRequests; output goes through Resources; errors are `DomainException` subclasses with a Persian message and a stable `code`.
- **Every tenant-owned model uses `BelongsToTenant`** and ULID keys. Child tables reference `(parent_id, tenant_id)` with a composite FK. Never query across tenants except inside `TenantContext::bypass()` with a comment explaining why.
- **Every new tenant endpoint is added to `tests/Feature/Tenancy/TenantIsolationTest.php`.**
- New permissions go in `PermissionCatalog` (+ `DefaultRoles`). New tenant settings go in `TenantSettingsRegistry`.
- Money is integer rial (`App\Support\Money\Money`). Dates are stored in UTC; Jalali conversion happens only at presentation (`JalaliDate`). Phones are E.164 via `PhoneNormalizer`.
- All user-facing text is Persian (`lang/fa`). Code and DB identifiers are English.
- **Never constructor-inject request-scoped services (anything touching `TenantContext`) into controllers or other long-lived objects.** Laravel reuses controller instances (and Octane reuses everything); use method injection or resolve at call time.
- Dates are stored as UTC by `StoresDatesInUtc` (included via `BelongsToTenant`); non-tenant models must `use StoresDatesInUtc` explicitly. APIs return UTC ISO-8601.
- Bearer tokens (QR, cart, session, tracking) travel in headers or bodies, never in logged URLs, and are stored only as SHA-256 hashes or derived with HMAC.
- Module dependencies point one way (Payments → Commerce → Catalog/Discounts → Core). When a lower module needs something from a higher one, define a contract in the lower module (e.g. `Commerce\Contracts\OnlinePaymentGate`) and bind it in the higher module's provider.
- An order's `payment_status`/`paid_total` are written only by `SyncOrderPaymentStatus`; gateway calls happen outside DB transactions and are always logged via `PaymentLog`.
- Wallet and points balances change only through `PostWalletTransaction`/`PostPointsTransaction` (locked, append-only, idempotency keys). Lock order: order → payment → wallet.
- Streamed responses (`streamDownload`) run after the middleware has cleared the tenant context: re-enter it with `TenantContext::runAs()` inside the callback.
- Tenant-bound tokenables (customers, kitchen devices) implement `TenantBoundTokenable`; the Sanctum check accepts their tokens only inside their tenant and while active.
- Kitchen state changes go through `UpdateKitchenItems` (lock order, then items; order sync via `TransitionOrder`); KDS routes take plain string ids checked against the actor's branch/station.
- Live screens never re-read everything blindly: they poll a `LiveVersion` key (bumped from model `saved` events, after commit) and get a 304 when nothing changed; polling pauses while the page is hidden.
- Don't reuse a `Route::model()` parameter name (`{order}`, `{product}`…) on a route that expects a raw string.

## Frontend (apps/web, packages/*)
- Next.js 16: `cookies()/headers()/params` are async; route protection lives in `src/proxy.ts`. Check `node_modules/next/dist/docs/` before using unfamiliar APIs.
- The staff token stays in an HttpOnly cookie. Only server code (`src/lib/api.ts`, Server Actions) talks to the API.
- Look & feel: build screens only from `@cafe/ui` + tokens (never hard-coded colours); icons from `lucide-react`; charts from `@cafe/ui` Charts (money charts via `components/MoneyCharts` — server components can't pass formatter functions); separators are `•` or `،`, never `·` (it looks like the Persian zero); every screen needs a calm empty state and must work in dark mode.
- Help centre: every screen has a topic in `apps/web/src/lib/help/` (steps + a worked example, visibility by the same permissions as the sidebar); a change to a screen updates its topic, a new screen adds one.
- Dashboard widgets are registered in `Insights\Support\WidgetCatalog` (permission, sizes, role defaults) and rendered in `app/dashboard/widgets/Widgets.tsx`; new phases add their widgets there instead of hard-coding cards on the overview.
- Storefront (`/s/[tenant]`): the browser never calls the API; cart/customer/table tokens are HttpOnly cookies scoped to `/s/{tenant}` (`lib/storefront.ts`), visitor calls go through `sf()` (forwards the client IP — `TRUSTED_PROXIES` must include the web server). Menu/product pages stay free of personal data; per-visitor state loads in `StoreProvider`. The tenant brand colour is applied only through `brandCss()` (contrast-safe in both themes). Modifier `max_select = 0` means unlimited.
- Stock changes only through `Inventory\Actions\PostStockMovement` (locked, append-only, idempotency keys); quantities are in the ingredient's base unit (g/ml/pcs) and costs are integer rial per 1000 base units (`Units` converts entry units and prices). Sales decrement via `ConsumeOrderStock` on `OrderPlaced` and reverse on cancel/reject.
- Labour cost comes only from `Operations\Support\Payroll` (attendance minutes × hourly rate; monthly salaries ÷ `staff.monthly_hours`, rounded to whole toman). Shift/attendance times go to the API as ISO instants with the café's offset (`localToIso` in `lib/operations-types.ts`), never naive local strings.
- Reports read the `Analytics` aggregates (`daily/hourly/product_metrics`), never raw orders over long ranges. Any new source of a metric must mark its business day with `DirtyDays::mark()` (model event in `AnalyticsServiceProvider`); rows are rebuilt only by `RollupDay`. What counts as a sale is `Analytics\Support\SalesRules`. Exports: CSV with BOM, XLSX via `App\Support\Export\XlsxWriter`, money in toman, Jalali dates as text; PDF = the `/print/*` page.
- Plan entitlements: ask `App\Support\Entitlements\EntitlementGate` (resolve at call time) — `feature:<key>` middleware on gated route groups, `ensureCanAdd()` before creating limited things (branches, staff, products), skip side effects when a feature is off (but always run reversals). New gated features go in `Billing\Support\FeatureCatalog` + `FeatureLabels` + the web `SCREEN_FEATURES`. Read-only subscriptions refuse writes in `ResolveTenant`; never lock customers out of their wallet. Subscription fees use the platform gateway (`BillingGateway`), never a café's merchant.
- Marketplace (`/explore`, user-facing name «خوراک‌گردی»: every kind of food store, never «کافه‌گردی») reads only the `marketplace_stores` projection, built by `Marketplace\Actions\ProjectStore` from an explicit allow-list and serialised by `StorePresenter` key lists. New public facts go through both; a model whose change affects them must be added to the `MarketplaceSync` events in `MarketplaceServiceProvider`. Never expose `tenant_id`, internal ids or anything the café didn't publish.
- Ads: campaigns (`Advertising`) are served only from the `ad_slots` projection (`ProjectCampaign`) through Marketplace's `SponsoredContent` contract, and only for cafés still in `marketplace_stores`. Sponsored results come only from stores that already match the search, are labelled «تبلیغ» and removed from the organic list. Ad links are always the café's own pages, built server-side. Payments are billing invoices of kind `ad` fulfilled by an `InvoiceFulfiller` (never void another kind's invoices); impressions/clicks only via signed `AdTokens` and `RecordAdEvent` (deduped counters, no raw events). Text over photos/art uses `on-media`/`scrim` tokens.
- Uploaded photos go through `App\Support\Media\ImageProcessor` (GD → WebP, EXIF orientation, metadata stripped, pixel cap); never store the raw upload for public display.
- Menu mood (`hot`/`cold`) uses the `--mood-*` tokens via `data-mood` on a container; always pair the colour with the flame/snowflake chip. Storefront product visuals come from `components/store/ProductVisuals` (photo or illustration fallback, varied per item by `StoreArt`); photo-less heroes and covers use generated art (`StoreHeroArt`, explore `CoverArt`), never a flat fill.
- `Dialog` variants (`modal`/`drawer`/`sheet`) own their height caps; only the body scrolls. Don't give an ancestor of `position: fixed` UI a lingering `transform` (animations use `fill-mode: backwards`).
- Glass (`.glass` / `.glass-light`) only on sticky bars and overlays over photos, never on list cards (readability, low-end Android). Pre-order rules live in `Commerce\Support\PreorderSchedule` (used by both the slot picker API and the pricer); pre-orders reach the kitchen via `kitchen:release-preorders`, not at placement.
- UI text is Persian and RTL: logical CSS (`ms-/me-/ps-/pe-/start/end`), `@cafe/ui` components, `@cafe/locale` formatters. Never use native `type="time"`/`type="date"` inputs (they follow the browser locale and show English); use `ClockSelect` or a Jalali picker instead.

## Local ports
8000, 8010 and 3000 are used by other projects on this machine. Use API :8765 and web :3765.
