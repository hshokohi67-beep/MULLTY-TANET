# Legacy source snapshot (read-only reference)

Frozen copy of the two WordPress plugins analysed in Phase 0. **Never deploy or port this code.**
It exists only so the business rules can be looked up during implementation and migration.

| Plugin | Version header | Base | Newer files overlaid from patch zips (2026-07-28) |
|---|---|---|---|
| `live-cafe-menu` | 4.1 | `Local Sites/dentall-clinic/.../plugins/live-cafe-menu` (2026-07-26) | `live-cafe-menu.php` (_27), `includes/lcm-push-helpers.php` (_28), `public/menu-template.php` + `public/checkout-template.php` (_25), `public/menu-grid-partial.php` (_22) |
| `lcm-customer-club` | 1.0.0 (DB schema 1.4) | `Local Sites/dentall-clinic/.../plugins/lcm-customer-club` (2026-07-26) | `admin/class-lcm-admin.php` + `admin/views/view-settings.php` (_12), `includes/class-lcm-ajax.php` (_10) |

The overlay rule was: for each file, keep the newest copy among the installed plugin and all
`~/Downloads/*-updated*.zip` patch archives. All PHP files pass `php -l` (PHP 8.3).

`~/Downloads/cafe-live-menu-pro.zip` (2026-06-30, prefix `clmp_`) is an **older, separate prototype**
(custom post types `clmp_item`/`clmp_bundle`, option groups, shortcode `[clmp_menu]`). It is not part of
the live system and was not merged. It is mentioned in the discovery report only as a design reference.
