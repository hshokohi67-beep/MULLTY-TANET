# Phase 20 report: occasions calendar, seasonal menus, richer product details, public club page

## Built

### Occasions calendar (module `Occasions`)
**Platform data:**
- `occasions` holds 36 seeded entries: national, joyful religious, mourning, Ramadan, global, drink days and seasons. Each has a description, ideas, campaign text, lead days, tone and a seasonal theme.
- `occasion_dates` holds each occurrence as Gregorian days.
- `OccasionSeed::install()` only adds missing keys, so admin edits survive deploys.

**Dates:**
- Jalali and Gregorian dates are exact.
- Chaharshanbe Suri and Black Friday are rules.
- Hijri dates use ICU's civil Islamic calendar computed in UTC. Checked against 2025 Iranian official dates (Fitr, Ghadir, Ashura, Nimeh Sha'ban, Mab'ath): 4 of 5 were exact and 1 was a day early. Hijri dates therefore show «تاریخ تقریبی» ("approximate date") until a platform admin confirms or moves them.
- Confirmed dates are never recomputed.

**Per café:**
- `tenant_occasions` stores the café's answer (`planned`, `dismissed`, `done`) and `notified_at`.
- New settings: `occasions.kinds` (which kinds it follows) and `occasions.notify`.

**Telling the café:**
- `occasions:run` runs daily at 10:00 Tehran. It resolves dates, then sends a browser push once per occurrence to staff with `occasions.view`, through the new `PushNotifier::staffNotice()`.
- Dismissed occurrences are never notified.
- Mourning days get a respectful reminder with no campaign text and no theme.
- The bell («نیاز به توجه»): an `occasions` alert for in-lead occurrences without an answer.
- A dashboard widget «مناسبت‌های پیش رو» ("upcoming occasions"). It is a default for owner and manager.

**Panel:**
- `/dashboard/occasions`: occasion cards with date, countdown, the «تقریبی» and holiday badges, ideas, a copyable campaign text, and actions:
  - «ساخت تخفیف»: the discount editor opens prefilled with the name and dates;
  - «منوی مناسبتی»: the collection editor opens prefilled with title, dates and theme;
  - «برنامه دارم / رد کن / انجام شد / برگرداندن» ("I have a plan / dismiss / done / undo").
- Preferences (kinds and reminders) for owners.
- New permission `occasions.view`, given to manager; owners have every permission. Existing tenants receive it through `permissions:sync`.

**Platform:**
- `/platform/occasions`: confirm or move Hijri dates, edit occasions, add a one-off occasion with its date.

### Seasonal menus («منوی مناسبتی»)
- `menu_collections` and `menu_collection_products` are tenant-owned, with composite FKs.
- Each collection has a title, subtitle, up to 20 products, start/end, a theme (nowruz, yalda, autumn, winter, summer, ramadan) and an occasion key.
- The public menu carries `collections` with their window. The menu shows active ones as a brand-tinted strip under the deals slider.
- `ShopChrome` draws `SeasonDecor`:
  - a few faint pieces drifting down *behind* the page (`-z-10` in an isolated stacking context), so cards, text and prices always cover them;
  - static when the visitor prefers reduced motion;
  - no theme is offered for mourning occasions.
- Panel: «منو ← منوی مناسبتی» with a searchable product picker and Jalali dates.

### Richer product details (from the kaicoffeeco.com review)
**New product fields:**
- `name_en`, `ingredients`;
- `allergens`: platform list of 9 (gluten, dairy/lactose, egg, nuts, peanut, soy, sesame, fish, caffeine);
- `caffeine_source`, `caffeine_dose`;
- `is_new`: manual, or automatic for 21 days after creation. Products created in the café's first week don't count, so an imported menu isn't all «جدید».

**Storefront:**
- a «جدید» ("new") chip on cards;
- in the sheet: the English name, ingredients, an allergen warning, and caffeine source and dose under the caffeine tile;
- prev/next arrows move through products of the same section (or the current search results).

**Import:** Excel/CSV import reads «نام انگلیسی», «مواد تشکیل‌دهنده» and «حساسیت‌زا». Allergen words map to platform keys, e.g. «گردو» → nuts and «شیر» → dairy.

### Public club page (`/s/{tenant}/club`)
- `GET /public/club` returns an allow-list of program facts and nothing about customers. It returns 404 when the club is off or not in the plan.
- The page has:
  - a hero and «چطور کار می‌کند؟» ("how it works") steps;
  - a tier path, each tier in its own colour with a contrast-safe fill via `brandTokens`;
  - cashback rules;
  - birthday and referral gifts;
  - join links.
- It is linked from the shop header («باشگاه») and from the account card.
- The account card now shows the customer's tier as a pill in the tier's colour. This answers the earlier question: before this phase the tier colour appeared nowhere a customer could see it.

### Also
- The menu tabs no longer mark «آیتم‌های منو» ("menu items") active on the import and collections pages.
- Help:
  - a new topic «تقویم مناسبت‌ها و منوی مناسبتی» ("occasions calendar and seasonal menus");
  - the menu topic covers the new product fields;
  - the storefront topic covers the collection strip, the club page and prev/next;
  - a new platform topic covers the occasions admin.

## Tests and checks
- **API: 593 tests**, all passing (1 skipped as before). New:
  - `OccasionsTest`:
    - Jalali, Hijri and Gregorian dates, including the Chaharshanbe Suri and Black Friday rules;
    - confirmed dates kept;
    - the seed never overwrites admin edits;
    - a one-off occasion and an edit of a rule-based one;
    - upcoming, answers and the kinds filter;
    - permissions;
    - notify once, never when dismissed;
    - mourning without promotion;
    - the bell alert and the widget.
  - `MenuCollectionTest`:
    - a collection's window and theme on the menu, and its end;
    - other tenants' products rejected;
    - product details, allergen validation and the automatic «جدید»;
    - the public club allow-list.
  - The import test now covers the new columns.
  - Isolation entries for collections and upcoming occasions.
- **Static checks:** Larastan level 6 reports 0 errors; Pint passes.
- **Web:** typecheck, lint, `npm test` and build all pass.
- **In the browser (Chrome):**
  - the menu with the «طعم‌های پاییزی» strip and autumn decoration;
  - the product sheet with ingredients, allergens and caffeine;
  - the club page with coloured tiers;
  - the occasions page with tone bands and «تقریبی» badges;
  - «ساخت تخفیف» prefilling the discount form;
  - the collections page;
  - the dashboard widget.

## Notes
- Owners who already customised their dashboard layout add the «مناسبت‌های پیش رو» widget from «چیدمان». Default layouts include it.
- Seeded occasion texts should be reviewed by the owner, especially the religious ones; they can be edited in `/platform/occasions`.
- Moved to Phase 21: product reviews, café stories, a branch picker with branch photos, a link-card landing template, and a careers section.
