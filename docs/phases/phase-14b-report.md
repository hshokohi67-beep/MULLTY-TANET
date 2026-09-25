# Phase 14b: Storefront art, city photos, «خوراک‌گردی» (Report)

Plan: `phase-14b-plan.md`. Status: done.

## 1. What changed

### Every store's own menu (`/s/{tenant}`)

- **Hero**
  - Without a cover photo, the flat dotted fill is replaced by generated art (`StoreHeroArt`): the store's brand gradient, soft glows, a scatter of food icons and a large faint monogram.
  - The logo (or a monogram tile) now sits in the hero.
  - Text and scrims use the `on-media`/`scrim` tokens; the hard-coded white/black and hex colours are gone.
- **Product tiles without a photo**
  - Each item gets one of three glow/gradient placements and a faint pattern of its own illustration (`TilePattern`), chosen from its id.
  - Neighbouring items no longer look identical.
  - Mood colours and the hot/cold rules are unchanged.
- **Category headings**: a tinted illustration tile (or the category photo), the name, a count chip and a fading rule.
- **Footer**
  - Logo, address, call/Instagram/directions buttons.
  - The weekly hours with today highlighted («(امروز)», "today").
  - A quiet «ساخته‌شده با کافه‌یار» ("made with Kafeyar") line.

### City photos

- `marketplace_place_images` (platform-level).
- The platform uploads, replaces or removes a photo per city on `/platform/marketplace` («تصویر شهرها», "city photos").
  - Only cities that have stores can get one.
  - Images are re-encoded to 1200 px and 600 px WebP under `places/`; old files are deleted.
- The public `home` returns `image_url` per city.
- The «خوراک‌گردی» ("food-hopping") city tile shows the photo under a scrim, and falls back to the art tile otherwise.

### «خوراک‌گردی»

- Every user-facing «کافه‌گردی» ("café-hopping") is now «خوراک‌گردی»: web, API messages, permission label, dashboard widget, and the placement names (via a data migration).
- The header icon is now cutlery.
- Copy is generalised to cover every kind of food store:
  - «جای خوشمزه‌ی بعدی را پیدا کنید» ("find your next tasty spot");
  - counts read «مکان» ("places");
  - «کسب‌وکارها» ("businesses");
  - «کافه، رستوران یا شیرینی‌فروشی دارید؟» ("do you run a café, restaurant or pastry shop?").
- The command palette finds it under both names.
- The URL `/explore` and all code identifiers are unchanged.

## 2. Verification

- API: 497 tests green, including the new `PlaceImagesTest`:
  - upload, validation, replacement (old file removed) and removal;
  - only the platform can manage photos;
  - the public `image_url`;
  - the placement rename.
- Larastan 0 errors, Pint clean.
- Web: typecheck, lint and build green.
- Visual check (headless Chrome):
  - storefront «کافه نارنج» ("Narenj Café") at 390 px light;
  - «کافه نمونه» ("Sample Café") at 390 px dark and 1280 px light;
  - `/platform/marketplace` with the city tiles;
  - «خوراک‌گردی» home at 390 px.
- Tweaked during the check: pattern icons were clipped at the tile edges and are now kept inside.
