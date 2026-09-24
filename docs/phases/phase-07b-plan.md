# Phase 7b: Stories, Hot/Cold Menu Mood and Storefront Visual Polish (Plan)

**Status:** implemented (see `phase-07b-report.md`)
**Depends on:** Phase 7.
**Requested by:** the owner (2026-09-25).

The owner's decisions:
- stories are **images only**, shown for **24 hours** by default;
- the warm/cold colours are Claude's choice;
- the storefront must look as polished as the management panel.

## 1. Stories

### Data
`stories` table (tenant-owned, ULID):

| Field | Notes |
|---|---|
| `branch_id` | Nullable = all branches. Composite FK |
| `image_path`, `thumb_path`, `width`, `height` | The image files and size |
| `caption` | ≤ 200 characters |
| `link_type` | `none` / `product` / `category` / `url` |
| `link_target` | ID or https URL |
| `cta_label` | ≤ 30 characters |
| `starts_at`, `ends_at` | UTC. The default is now → +24 h |
| `sort`, `is_active` | Order and on/off |
| `views`, `clicks` | Counters |

### Images
`Support\Media\ImageProcessor` (GD) re-encodes every upload:
- it applies the EXIF orientation;
- the story image is WebP with the long side ≤ 1600 px;
- the ring thumbnail is a 240 px square WebP.

Re-encoding strips metadata (a phone photo's GPS location never goes public) and neutralises polyglot files. Uploads accept PNG/JPEG/WebP up to 8 MB, min 400 px.

### Staff API (`storefront.manage`, added to owner/manager defaults)

| Endpoint | Purpose |
|---|---|
| `GET /stories` | List, including expired ones, with a status: scheduled / live / expired / off |
| `POST /stories` | Multipart: image + fields |
| `POST /stories/{story}` | Update; the image is optional |
| `DELETE /stories/{story}` | Delete |
| `PUT /stories/order` | Reorder |

A product or category link must belong to the same tenant, and a URL link must be `https`.

### Public API

| Endpoint | Purpose |
|---|---|
| `GET /public/stories?branch=` | Live stories only, ordered, with the product slug resolved for links |
| `POST /public/stories/{id}/seen` | Counts a view. Throttled, and deduplicated per IP per day in cache |
| `POST /public/stories/{id}/click` | Counts a click. Same throttle and deduplication |

### Panel
New page «استوری‌ها» ("stories"):
- cards in 9:16 frames with the live/scheduled/expired badge and views/clicks/CTR;
- an editor drawer with a live phone-frame preview, image picker, caption, link (product/category search, or URL), button label, duration (24 h, 3 days, 7 days, or a custom Jalali end), branch and active;
- drag to reorder.

A dashboard widget `stories` shows live stories, views and clicks for the range. It is registered in `WidgetCatalog`.

### Storefront
- **Story ring row** at the top of the menu: a brand-colour ring when unseen, grey when seen. Seen state is kept in `localStorage`, per viewer only.
- **Full-screen viewer:**
  - segmented progress bars; 5 s per story; tap start/end for previous/next (RTL-aware); hold to pause;
  - swipe down or Esc to close;
  - the caption on a soft gradient; the CTA opens the product sheet directly, scrolls to the category, or opens the URL;
  - the next image is preloaded;
  - `prefers-reduced-motion` turns the auto-advance animation off (manual next only);
  - screen-reader labels ("استوری ۲ از ۵" / "story 2 of 5").

## 2. Hot/cold menu mood
- **Data:** `categories.temperature` and `products.temperature`, each nullable `hot` / `cold`. A product without its own value inherits its first category's value. The public menu returns the effective `temperature`.
- **Panel:** «حس دما» ("temperature feel") select on the category form (گرم / سرد / خنثی: hot / cold / neutral), and an override on the product form.
- **Look:** tokens `--mood-hot` (warm amber/terracotta, not alarm red) and `--mood-cold` (clear sky blue), each with its own dark-mode step.
  - Product cards and photos get a soft radial glow in the mood colour behind the image, visible at rest (phones have no hover) and stronger on hover, focus or press.
  - A small flame/snowflake chip next to the name, so colour is never the only cue.
  - Desktop hover adds a gentle steam curl (hot) or frost shimmer (cold). Reduced motion turns it off.

## 3. Storefront visual polish (the same care as the panel)
- **Hero:**
  - an optional **cover image** per café (new `tenant_branding.cover_path`, uploaded in Settings, re-encoded like stories);
  - over it, the logo, name, open/closed pill, branch, and a short info row (hours today, delivery or pickup);
  - without a cover, a tasteful brand-tinted gradient pattern.
- **Product cards:**
  - a larger rounded photo with the mood glow;
  - a placeholder without a photo: a brand/mood tinted tile with a category icon instead of a bare letter;
  - price in a pill; a clear "ناموجود" ("unavailable") state; a featured badge.
- **Featured row:** larger cards with a photo overlay and price chip.
- **Category chips:** with icons and a sliding active indicator.
- **Product sheet:** a full-bleed photo on top, the mood chip, and nutrition as small tiles.
- **Cart bar and checkout:** refined spacing, step headings, a sticky total bar on phones.
- **Loading and empty states:** skeletons matching the real layout (`loading.tsx` for menu/cart/account); calm empty states with icons.
- **Micro-motion:** an add-to-cart "fly" pulse on the cart bar, and a sheet spring. All short, and off under reduced motion.
- **Visual check in Chrome** at 390 px and at desktop width, light and dark, for the menu, sheet, stories viewer, cart, account and panel stories page. Fix what looks off.

## 4. Tests
- **Stories:**
  - create, including image re-encoding to WebP with the thumb made and EXIF gone;
  - validation: link belongs to the tenant, https only, dimensions;
  - schedule: live/expired/scheduled;
  - the public list shows only live stories for the branch;
  - seen/click counting with deduplication;
  - permission; reorder; delete removes the files.
- **Temperature:** inheritance and override in the public menu.
- **Cover upload.**
- **Isolation harness:** another tenant's story can't be seen, edited, counted or linked; foreign product and category IDs are rejected.
- **Web:** typecheck, lint, build; the visual pass above.

## 5. Risks
- **Big phone photos (10 MB+):** GD memory. The upload limit is 8 MB and processing checks the pixel count (≤ 40 MP) before decoding.
- **Stories hurting menu speed:** only the 240 px thumbs load with the menu; full images load when the viewer opens, with the next one preloaded.
