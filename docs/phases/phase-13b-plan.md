# Phase 13b: Map pickers, locations, a professional «کافه‌گردی» (Plan)

The owner asked for four things:
1. The promised map selection for branches and delivery zones (a hint in the branch form still said "added in the ordering phase").
2. A province → city → area picker at the top of «کافه‌گردی» that opens that area's cafés automatically.
3. Research-backed marketplace features.
4. A more professional search (economical, featured, discounted…).

## Research summary

- Yelp's most-used filter is "Open now"; takeout, outdoor seating and delivery are in the top five for restaurants ([Search Engine Land](https://searchengineland.com/exclusive-the-most-popular-yelp-search-filters-377209)).
- Google Maps offers price, rating, cuisine and hours ([Search Engine Land](https://searchengineland.com/google-search-lets-filter-restaurants-whats-open-now-price-190003)).
- 2026 discovery apps use a map view, neighbourhood filters, saved lists (favourites), curated collections, dietary needs and dish search ([Chop Dawg](https://www.chopdawg.com/how-to-build-a-location-based-discovery-app-venues-reviews-and-recommendations-in-2026/), [choosemy.food](https://choosemy.food/blog/best-restaurant-apps.html)).
- SnappFood finds places by area, name or dish and highlights discounts ([SnappFood](https://food.snapp.ir/)).

**Chosen now:**
- area hierarchy;
- quick filter chips;
- offers and discount badges;
- autocomplete (cafés, places, categories, dishes);
- map view;
- curated collections;
- dietary filters from menu tags;
- favourites (on this device);
- share;
- more sort options.

**Later, recorded as the roadmap:**
- ratings and reviews (needs an order rating first);
- photo galleries and events;
- trending (from analytics);
- personalised recommendations;
- sponsored placements (Phase 14).

## Changes

**Backend**
- `branches.district` («محله / منطقه»): form, validation, resource and projection.
- Projection additions:
  - `district`;
  - `offers`: current automatic discounts only; code discounts stay private;
  - `has_offer`, `dietary` (from active products' tags), `closes_late`, `free_delivery`;
  - a `Discount` change re-projects the café.
- `home` adds `places` (province → city → district, with counts) and `collections` (work and study, late night, budget, with offers, breakfast, new).
- `stores` adds these filters:
  - `province`, `district`;
  - `featured`, `offers`, `delivery`, `online_payment`, `preorder`, `dine_in`, `late`, `new`;
  - `dietary[]`, `stores[]` (favourites).

  It also adds sorts `popular`, `price_asc` and `price_desc`, and cards include coordinates, district and offers.
- `GET /public/marketplace/suggest?q=`: cafés, places, categories and dishes, each list short.

**Web**
- A branch map picker in the branch form (tap or drag the pin, or use my location).
- A delivery-zones map (branch pin plus each radius drawn).
- `/explore`:
  - a sticky location bar (province → city → area) that navigates on change and remembers the choice on this device;
  - quick chips;
  - an autocomplete dropdown;
  - a list/map toggle (pins with mini cards);
  - collections;
  - favourites (heart; «علاقه‌مندی‌ها», "favourites", filter);
  - offer badges.
- Profile page: offers, district, share, favourite.
- Login: a platform admin without cafés lands on `/platform`.

## Security

- Offers come only from automatic, active discounts (no codes, no usage data). `stores[]` accepts at most 50 valid slugs.
- The suggest endpoint is capped (query ≤ 40 characters, 5 items per group) and throttled.
- The same leak test covers the new fields and `suggest`.
