# Phase 20 plan: occasions calendar, seasonal menus, richer product details, public club page

Phase 20 does two things:
- It builds the owner's idea, a platform calendar of Iranian and global occasions that tells every café in time, so it can prepare its menu.
- It adopts the best ideas from the kaicoffeeco.com review.

The rest of that review (reviews, café stories, branch picker, link-card page, careers) moves to Phase 21.

## 1. Occasions calendar (new module `Occasions`)

### Data (platform-owned, not tenant data)
**`occasions`** holds the catalogue. The platform admin edits it, and a seeder ships about 60 entries.
- **Identity:** `key`, `title`, `description`.
- **`kind`:**
  - `national`: Nowruz, Sizdah Bedar, Chaharshanbe Suri, Yalda, Mehregan, Sepandarmazgan, Tirgan, school start, Teacher's Day, Student Day…;
  - `religious_joy`: Eid al-Fitr, Ghadir, Mab'ath, Nimeh Sha'ban, the Mother's Day and Father's Day births…;
  - `mourning`: Tasua/Ashura, Arbaeen, 28 Safar, the Qadr nights, Fatimiyya…;
  - `ramadan`: the month itself, iftar/sahari hours;
  - `global`: Valentine's Day, Women's Day, Christmas/New Year, Halloween, Black Friday;
  - `drink_day`: International Coffee Day (1 Oct), World Tea Day (21 May), World Chocolate Day (7 Jul)…;
  - `season`: first day of each season, the start of hot and cold weather.
- **`calendar`** (`jalali` | `hijri` | `gregorian`), `month`, `day`, `duration_days`.
- **`lead_days`:** how early cafés hear about it. Defaults: 14, 21 for Nowruz and Yalda, 3 for mourning days.
- **`is_holiday`:** an official holiday, so footfall changes.
- **`tone`:** `festive` | `calm` | `mourning`. Mourning days come with advice, not promotions: pause festive themes, keep music low, check opening hours.
- **`ideas`:** menu ideas and an example campaign text, e.g. Yalda → pomegranate, watermelon, nuts and a «شب یلدا» box.
- **`theme`:** seasonal decoration key, see §2.

**`occasion_dates`:** the resolved Gregorian start date per year, plus `confirmed`.
- **Computing dates:** Jalali and Gregorian are exact. Hijri uses ICU (`ext-intl`, already installed and verified on this machine).
- **Moon-sighting correction:** Iran's official Hijri dates can differ by a day, so dates in the next 60 days are shown as «تاریخ تقریبی» ("approximate date") until the platform admin confirms or moves them in the panel.
- **Scheduling:** `occasions:resolve` runs daily and fills the current and next year.

### Per café
**`tenant_occasions`** (tenant-owned):
- the café's choice per occasion date: `planned` | `dismissed` | `done`;
- links to what it created (discount, collection).

New settings in `TenantSettingsRegistry`:
- `occasions.kinds`: which kinds the café cares about. All are on by default; a café can switch off, say, `global`.
- `occasions.notify`: on/off.

### Telling the café
1. **Dashboard widget «مناسبت‌های پیش رو»** ("upcoming occasions", `WidgetCatalog`; owner and manager by default):
   - the next 45 days: title, days left, the tone colour and an «تقریبی» ("approximate") badge when unconfirmed;
   - one tap opens the occasion card.
2. **Occasion card:** what it is, the ideas, and one-click actions:
   - «ساخت تخفیف»: opens the discount editor prefilled with the dates and «نمایش روی منو»;
   - «ساخت مجموعه‌ی منو» (see §2);
   - «پیشنهاد روز»: pick products for that day;
   - «یادم بنداز / رد کن» ("remind me / dismiss").
3. **Bell + browser push to staff devices** (`catalog.manage` or `discounts.manage`), sent `lead_days` before:
   - `occasions:notify` runs daily at 10:00 in the café's timezone;
   - it is idempotent, so the same occasion is never notified twice;
   - nothing is sent for dismissed or switched-off kinds.
4. **Alerts:** «یلدا ۵ روز دیگر است و هنوز برنامه‌ای ندارید» ("Yalda is in 5 days and you have no plan yet") appears in «چه چیزی نیاز به توجه دارد؟».
5. **Mourning days:**
   - a calm notice, no promotional push;
   - café SMS campaigns scheduled on those days get a warning in the campaign screen.

### Platform admin (`/platform/occasions`)
- List and edit occasions.
- Confirm or move each year's Hijri dates.
- Add a one-off occasion, e.g. a national match day.

## 2. Seasonal menus: collections and themes

**`menu_collections`** (tenant-owned):
- `title` and `subtitle` («طعم‌های پاییزی»);
- `product_ids` (composite FK);
- `theme`;
- `starts_at` / `ends_at`;
- optional `occasion_date_id`.

On the menu:
- An active collection appears as a highlighted strip above the menu, under the deals slider, in the café's brand colour with the theme's art.
- It ends by itself.

`storefront.season_theme` is set from a collection:
- **Themes:** autumn leaves, Yalda pomegranates, winter snow, Nowruz greens, Ramadan lanterns, summer.
- **Placement:** only in the hero, the collection strip and the page edges; never over prices or text.
- **Motion:** at most a few slow particles, and none with `prefers-reduced-motion`.
- **Mourning:** a mourning occasion never offers a festive theme.

## 3. Richer product details (from the review)
**New product fields:**
- `name_en`, for tourists and an English menu toggle later;
- `ingredients`, separate from the description;
- `allergens`: a fixed platform list (gluten, dairy/lactose, egg, nuts, peanut, soy, sesame, caffeine), shown with a warning icon;
- caffeine source and dose: «قهوه، ۲ شات» next to the mg we already store;
- `is_new`: manual, or automatic for 21 days after a product is created → a «جدید» ("new") chip.

**Product sheet:**
- prev/next arrows and swipe to move through products in the same category;
- deep link `?p=slug` that opens the sheet.

**Import:** the Excel import also accepts the new columns (`name_en`, `ingredients`, `allergens`).

## 4. Public club page (`/s/{tenant}/club`)
- A story-style page built from the café's real loyalty setup:
  - tiers in order, with their colours and multipliers;
  - cashback rules;
  - birthday and referral rewards;
  - how points become wallet credit.
- One full-width section per tier, using generated art or a photo the café uploads; a side dot navigation.
- A «عضو شوید» ("join") button leads to sign-in.
- It is linked from the menu header and the landing page; the landing page gets an optional «باشگاه» ("club") section.
- It shows only published facts, never customer data.

## API (all new tenant endpoints go into the isolation test)
- `GET /occasions/upcoming`.
- `POST /occasions/{date}/state`.
- `GET/POST/PUT/DELETE /menu-collections`.
- `GET /public/club`: club facts, read-only.
- Public menu: `collections` and the active `season_theme`, computed after the cache like `deals`.
- Platform:
  - `GET/POST/PUT /platform/occasions`;
  - `POST /platform/occasion-dates/{id}/confirm`.

## Security
- Occasions are platform data, writable only by platform admins.
- Café state lives in tenant-owned rows.
- Push and alerts respect permissions.
- The club page exposes only program settings.
- Collections reference only the café's own products (composite FK).

## Tests
**Occasions:**
- resolving Jalali, Hijri and Gregorian dates, including across the year change;
- lead-day notification exactly once, never for dismissed or switched-off kinds;
- mourning gets no promotional push.

**Admin, collections and widget:**
- the admin confirm/move flow;
- a collection's window on the menu, and a product from another tenant rejected;
- the widget and alerts are permission-gated.

**Product fields:** validation and import.

**Club page:** shows published facts only.

**Web:** unit tests for the date and countdown helpers.

## Risks
- **Hijri dates:** approximate until confirmed. The UI says so clearly, and the platform admin confirms them.
- **Seasonal decoration and performance:** kept to CSS and a few SVGs. Disabled on low-end devices and when motion is reduced.
- **Seeded occasion texts:** reviewed with the owner before release, especially the religious ones.
