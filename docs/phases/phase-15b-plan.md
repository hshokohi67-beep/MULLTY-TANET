# Phase 15b: Role-aware help centre, go-live preflight, media paths (Plan)

Phase 15 (hardening) was done in a cloud session and verified and fixed locally (see `phase-15-report.md` §6). The owner also asked for a «راهنما» (help) section in each store's sidebar. It must explain every part of the platform in detail, with examples, and show each user only the parts that belong to their role.

## 1. Help centre (`/dashboard/help`, `/platform/help`)

**Content: typed data in `apps/web/src/lib/help/`.**

- **One topic per screen.** Each topic records:
  - who it is for: the permissions that open the screen (the same keys as the sidebar);
  - its plan feature, if any;
  - a summary;
  - steps with worked examples (real amounts, names and times);
  - tips and warnings;
  - FAQs;
  - related topics and a link to the screen.
- **Role guides:** owner, manager, cashier, kitchen, waiter. Each covers a day in the role and what to do first. A user sees their own role's guide; the owner sees them all, so they can train the team.
- **Platform guide** for platform admins, at `/platform/help`.

**UI.**

- A «راهنما» ("help") item in every user's sidebar.
- A «راهنمای این صفحه» ("help for this page") button in the top bar that opens the topic for the current screen.
- **Index:**
  - a "your role" card, fed by `memberships[].roles` added to `/auth/staff/me`;
  - search;
  - topics grouped like the sidebar;
  - topics outside the plan are marked.
- **Topic page:** a table of contents, numbered steps, example boxes, FAQs and next/previous links. A topic the user can't open gives 404.

## 2. Go-live preflight

`php artisan ops:preflight` prints each check and exits non-zero when a blocking one fails:

- production environment, debug off, app key set, HTTPS `APP_URL`;
- not SQLite, queue not `sync`;
- payment driver not fake, Zarinpal sandbox off, SMS provider not `log`;
- trusted proxies set, secure session cookies;
- no demo accounts (`admin@example.test`) and no demo cafés;
- at least one platform admin;
- storage link present;
- the backup disk configured.

## 3. Media paths without tenant ids

New uploads (logo, cover, product and category photos, stories, the WooCommerce importer) go under `t/{media_key}/…`, where `media_key` is a random per-tenant key, backfilled for existing tenants. Public image URLs then no longer carry the tenant id. Existing files keep their paths and keep working.

## 4. Tests

- **`me`:** returns role names.
- **Preflight:** fails in testing and lists the reasons, and passes with a production-like config.
- **Media paths:** uploads use the media key, and the tenant id never appears in a new public URL.
- **Web:** typecheck, lint and build, plus visual checks of the help centre (cashier vs owner, phone and desktop, light and dark).

## 5. Risks

- **Help content drifting from the product.** A new CLAUDE.md rule says a change to a screen updates its help topic.
