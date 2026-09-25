# Phase 14b: Storefront art, city photos, «خوراک‌گردی» (Plan)

The owner's requests after Phase 14:

1. Bring the new look into every store's own menu (`/s/{tenant}`), so it stops looking the same.
2. The platform can design and upload a photo for each city tile.
3. Rename the marketplace from «کافه‌گردی» to «خوراک‌گردی», so it covers every kind of food store.

## 1. Storefront (web only)

- **Hero without a cover photo:** the store's brand gradient, a scattered pattern of food icons, soft glows and a large faint monogram, instead of the flat dotted fill. The logo (or monogram) sits in the hero on every store. Text and scrims use the `on-media`/`scrim` tokens (the hero had hard-coded white/black).
- **Product tiles without a photo:** each item gets its own variation, chosen from its id:
  - gradient angle and glow position;
  - a faint pattern of smaller copies of its illustration.

  Mood colours are kept (hot/cold rules unchanged).
- **Category headings:** a tinted illustration tile (or the category photo), the name, and a count chip.
- **Footer:**
  - a card with the logo, address, today's hours highlighted in the weekly table, and call and Instagram buttons;
  - a quiet «ساخته‌شده با کافه‌یار» ("made with Kafeyar") line.

## 2. City photos

- **DB:** `marketplace_place_images` (platform-level):
  - `city` (unique), `image_path`, `image_small_path`;
  - re-encoded by `ImageProcessor` (1200 px and 600 px WebP), stored under `places/`.
- **API:**
  - `GET /platform/marketplace/places` lists the cities that have stores, with their photo;
  - `POST /platform/marketplace/places/image` (city + image), throttled like uploads;
  - `DELETE /platform/marketplace/places/image?city=` removes a photo;
  - public `home.places[].cities[]` gains `image_url`.
- **Web:**
  - «تصویر شهرها» ("city photos") on `/platform/marketplace`: a grid of city tiles with upload, replace and remove;
  - the explore city tile shows the photo under a scrim, and falls back to the current art tile.

## 3. Rename

- Every user-facing «کافه‌گردی» becomes «خوراک‌گردی» (web, API messages, permission label, widget, placements).
- A data migration renames the seeded placement texts.
- Copy that assumed only cafés is generalised. For example: «جای خوشمزه‌ی بعدی‌تان را پیدا کنید» ("find your next tasty spot"), result counts read «مکان» ("place"), and the house slide addresses «کسب‌وکار» ("business").
- The URL `/explore` and code identifiers stay.

## 4. Security

- City photo endpoints are `actor:platform` only.
- Uploads go through `ImageProcessor` (re-encoded, metadata stripped, pixel cap).
- The city is validated against cities that actually have stores.
- Public output adds only an image URL.

## 5. Tests

- Platform uploads, replaces and removes a city photo.
- A staff user gets 403; an unknown city gets 422.
- The public `home` returns `image_url`.
- The placement rename migration.
- Web: typecheck, lint and build, plus visual checks (storefront phone and desktop, light and dark; explore cities; platform page).

## 6. Risks

- Pattern layers on low-end phones: static markup only, no filters on the product tiles.
