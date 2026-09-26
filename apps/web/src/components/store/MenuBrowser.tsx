'use client';

import { createElement, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Flame, Search, SlidersHorizontal, Snowflake, Sparkles, X } from 'lucide-react';
import { cx, Dialog, EmptyState } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { initialOf } from '@/components/explore/Art';
import { applyMenuFilters, CALORIE_LABELS, caloriesUseful, isFiltering, menuTags, NO_FILTERS, SORT_LABELS, suggestionsFor, type MenuFilters } from '@/lib/menu-logic';
import type { Menu, MenuLayout, MenuProduct, Mood, PublicStory } from '@/lib/storefront-types';
import { CartPanel } from './menu/CartPanel';
import { FilterSheet } from './menu/FilterSheet';
import { ProductCard, priceLabel } from './menu/ProductCard';
import { ProductDetails } from './ProductDetails';
import { hasPhoto, illustrationFor, MoodChip, ProductPhoto } from './ProductVisuals';
import { variantOf } from './StoreArt';
import { StoriesBar } from './Stories';
import { useStore } from './StoreProvider';

const OTHER = '__other';

interface Section { id: string; name: string; mood: Mood | null; image: string | null; products: MenuProduct[] }

const LISTS: Record<MenuLayout, string> = {
  list: 'grid gap-3 md:grid-cols-2 lg:grid-cols-1',
  grid: 'grid grid-cols-2 gap-3 sm:grid-cols-3',
  compact: 'rounded-3xl border border-border bg-surface px-4',
};

function CategoryIcon({ s, className }: { s: Section; className?: string }) {
  return s.image ? (
    // eslint-disable-next-line @next/next/no-img-element -- tenant media, small square
    <img src={s.image} alt="" width={44} height={44} loading="lazy" className={cx('size-full object-cover', className)} />
  ) : (
    <span aria-hidden="true" data-v={variantOf(s.id, 3)} className={cx('photo-fallback flex size-full items-center justify-center', className)}>
      {createElement(illustrationFor(s.name, s.mood), { className: 'size-5', strokeWidth: 1.75 })}
    </span>
  );
}

/**
 * The menu: stories, a sticky bar (the café's name once the hero scrolls away, search, filters and
 * category chips that follow your scroll), a featured row and product cards in the café's layout.
 * On wide screens: categories | menu | cart. Cards open a sheet in place (and stay real links).
 */
export function MenuBrowser({ menu, stories, layout, showCalories }: { menu: Menu; stories: PublicStory[]; layout: MenuLayout; showCalories: boolean }) {
  const { tenant, store } = useStore();
  const [query, setQuery] = useState('');
  const [filters, setFilters] = useState<MenuFilters>(NO_FILTERS);
  const [sheet, setSheet] = useState(0); // >0 = open; the number re-keys the sheet so it starts from the current filters
  const [open, setOpen] = useState<MenuProduct | null>(null);
  const [active, setActive] = useState<string | null>(null);
  const [compact, setCompact] = useState(false);
  const chipsRef = useRef<HTMLDivElement>(null);

  const popular = useMemo(() => new Set(menu.insights?.popular ?? []), [menu.insights]);
  const calories = showCalories && caloriesUseful(menu.products);
  const tags = useMemo(() => menuTags(menu.products), [menu.products]);
  const maxPrice = useMemo(() => Math.max(0, ...menu.products.map((p) => p.price_from)), [menu.products]);

  const sections = useMemo<Section[]>(() => {
    const known = new Set(menu.categories.map((c) => c.id));
    const list = menu.categories
      .map((c) => ({ id: c.id, name: c.name, mood: c.temperature, image: c.image_url, products: menu.products.filter((p) => p.category_ids.includes(c.id)) }))
      .filter((s) => s.products.length > 0);
    const rest = menu.products.filter((p) => !p.category_ids.some((id) => known.has(id)));
    if (rest.length) list.push({ id: OTHER, name: list.length ? 'سایر' : 'منو', mood: null, image: null, products: rest });

    return list;
  }, [menu]);

  const featured = useMemo(() => menu.products.filter((p) => p.is_featured && p.is_available).slice(0, 10), [menu.products]);
  const filtering = query.trim() !== '' || isFiltering(filters);
  const results = useMemo(() => (filtering ? applyMenuFilters(menu.products, query, filters, menu.insights?.popular) : null), [filtering, menu, query, filters]);
  const count = useCallback((f: MenuFilters) => applyMenuFilters(menu.products, query, f).items.length, [menu.products, query]);

  // The café's name joins the sticky bar once the hero has scrolled away.
  useEffect(() => {
    const hero = document.getElementById('menu-hero');
    if (!hero) return;
    const io = new IntersectionObserver(([e]) => setCompact(!e.isIntersecting), { rootMargin: '-40px 0px 0px 0px' });
    io.observe(hero);

    return () => io.disconnect();
  }, []);

  // Scroll-spy: the section in view is highlighted in the chips / side list and kept visible.
  useEffect(() => {
    if (results) return;
    const observer = new IntersectionObserver((entries) => {
      const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
      if (visible) setActive(visible.target.id.replace('cat-', ''));
    }, { rootMargin: '-160px 0px -55% 0px' });
    document.querySelectorAll('[data-menu-section]').forEach((el) => observer.observe(el));

    return () => observer.disconnect();
  }, [results, sections]);

  useEffect(() => {
    chipsRef.current?.querySelector(`[data-chip="${active}"]`)?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
  }, [active]);

  const openBySlug = useCallback((slug: string) => {
    const product = menu.products.find((p) => p.slug === slug);
    if (product) setOpen(product);
  }, [menu.products]);

  const openCategory = useCallback((id: string) => {
    setQuery('');
    setFilters(NO_FILTERS);
    // A parent category may have no section of its own: go to its first child that has one.
    const child = menu.categories.find((c) => c.parent_id === id && document.getElementById(`cat-${c.id}`));
    const target = document.getElementById(`cat-${id}`) ?? (child ? document.getElementById(`cat-${child.id}`) : null);
    setTimeout(() => target?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
  }, [menu.categories]);

  const card = (p: MenuProduct) => (
    <ProductCard key={p.id} product={p} layout={layout} tenant={tenant} branchId={menu.branch.id} popular={popular.has(p.id)} showCalories={showCalories} onOpen={setOpen} />
  );

  // Removable chips for what's active, so a filter is never invisible.
  const activeChips: { key: string; label: string; clear: () => void }[] = [
    ...(filters.sort !== 'suggested' ? [{ key: 'sort', label: SORT_LABELS[filters.sort], clear: () => setFilters((f) => ({ ...f, sort: 'suggested' })) }] : []),
    ...(filters.calories !== 'any' ? [{
      key: 'kcal',
      label: filters.calories === 'max' ? `تا ${formatNumber(filters.maxCalories ?? 0)} کالری` : `${CALORIE_LABELS[filters.calories]} کالری`,
      clear: () => setFilters((f) => ({ ...f, calories: 'any', maxCalories: null })),
    }] : []),
    ...filters.moods.map((m) => ({ key: `m-${m}`, label: m === 'hot' ? 'گرم' : 'سرد', clear: () => setFilters((f) => ({ ...f, moods: f.moods.filter((x) => x !== m) })) })),
    ...filters.tags.map((t) => ({ key: `t-${t}`, label: tags.find((x) => x.key === t)?.label ?? t, clear: () => setFilters((f) => ({ ...f, tags: f.tags.filter((x) => x !== t) })) })),
    ...(filters.maxPrice !== null ? [{ key: 'price', label: `تا ${formatMoney(filters.maxPrice)}`, clear: () => setFilters((f) => ({ ...f, maxPrice: null })) }] : []),
    ...(filters.availableOnly ? [{ key: 'avail', label: 'فقط موجودها', clear: () => setFilters((f) => ({ ...f, availableOnly: false })) }] : []),
  ];

  const branch = store.branches.find((b) => b.id === menu.branch.id) ?? store.branches[0];
  const filterCount = activeChips.length;

  return (
    <div className="lg:mt-5 lg:grid lg:grid-cols-[11.5rem_minmax(0,1fr)_19rem] lg:items-start lg:gap-6">
      {/* Wide screens: the category list on the side. */}
      {sections.length > 1 ? (
        <nav aria-label="دسته‌ها" className="sticky top-4 hidden max-h-[calc(100dvh-2rem)] flex-col gap-1 overflow-y-auto rounded-3xl border border-border bg-surface p-2 lg:flex">
          {sections.map((s) => (
            <a key={s.id} href={`#cat-${s.id}`} aria-current={active === s.id && !results ? 'true' : undefined} onClick={(e) => { e.preventDefault(); openCategory(s.id); }}
              className={cx('flex items-center gap-2.5 rounded-2xl px-2 py-1.5 text-sm transition-colors', active === s.id && !results ? 'bg-brand-soft font-semibold text-text' : 'text-text-muted hover:bg-surface-muted hover:text-text')}>
              <span data-mood={s.mood ?? undefined} className="size-8 shrink-0 overflow-hidden rounded-xl"><CategoryIcon s={s} /></span>
              <span className="min-w-0 flex-1 truncate">{s.name}</span>
              <span className="tabular text-xs text-text-subtle">{formatNumber(s.products.length)}</span>
            </a>
          ))}
        </nav>
      ) : <div className="hidden lg:block" />}

      <div className="min-w-0">
        {stories.length ? (
          <div className="mt-4 lg:mt-0">
            <StoriesBar stories={stories} onOpenProduct={openBySlug} onOpenCategory={openCategory} />
          </div>
        ) : null}

        <div className="glass sticky top-0 z-20 -mx-4 mt-3 rounded-b-3xl px-4 pt-3 lg:mx-0 lg:mt-0 lg:rounded-3xl lg:px-3">
          {compact ? (
            <div className="mb-2.5 flex items-center gap-2 text-sm">
              {store.branding?.logo_url ? (
                // eslint-disable-next-line @next/next/no-img-element -- tenant media
                <img src={store.branding.logo_url} alt="" className="size-7 rounded-lg object-contain" />
              ) : <span aria-hidden="true" className="flex size-7 items-center justify-center rounded-lg bg-brand text-xs font-black text-on-brand">{initialOf(store.name)}</span>}
              <span className="truncate font-bold">{store.name}</span>
              {branch ? (
                <span className="ms-auto inline-flex shrink-0 items-center gap-1.5 text-xs text-text-muted">
                  <span aria-hidden="true" className={cx('size-2 rounded-full', branch.is_open ? 'bg-success' : 'bg-warning')} />{branch.is_open ? 'باز است' : 'بسته است'}
                </span>
              ) : null}
            </div>
          ) : null}

          <div className="flex gap-2">
            <label className="relative block flex-1">
              <span className="sr-only">جست‌وجو در منو</span>
              <Search className="pointer-events-none absolute start-4 top-1/2 size-4 -translate-y-1/2 text-text-subtle" aria-hidden="true" />
              <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="جست‌وجو در منو…"
                className="h-12 w-full rounded-2xl border border-border bg-surface ps-11 pe-11 text-sm shadow-[var(--shadow-sm)] transition-shadow placeholder:text-text-subtle focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none [&::-webkit-search-cancel-button]:hidden" />
              {query ? (
                <button type="button" onClick={() => setQuery('')} aria-label="پاک کردن جست‌وجو" className="absolute end-2 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-full text-text-muted hover:bg-surface-muted">
                  <X className="size-4" />
                </button>
              ) : null}
            </label>
            <button type="button" onClick={() => setSheet((n) => n + 1)} aria-label={filterCount ? `فیلترها (${formatNumber(filterCount)} فعال)` : 'فیلتر و مرتب‌سازی'}
              className={cx('relative flex h-12 shrink-0 items-center gap-1.5 rounded-2xl border px-3.5 text-sm font-semibold shadow-[var(--shadow-sm)] transition-colors',
                filterCount ? 'border-brand bg-brand-soft text-text' : 'border-border bg-surface text-text-muted hover:text-text')}>
              <SlidersHorizontal className="size-4" aria-hidden="true" /><span className="hidden sm:inline">فیلتر</span>
              {filterCount ? <span className="absolute -end-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-brand text-[11px] font-bold text-on-brand ring-2 ring-surface">{formatNumber(filterCount)}</span> : null}
            </button>
          </div>

          {activeChips.length ? (
            <div className="no-scrollbar -mx-4 mt-2 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:px-0">
              {activeChips.map((c) => (
                <button key={c.key} type="button" onClick={c.clear} aria-label={`حذف فیلتر ${c.label}`}
                  className="inline-flex h-8 shrink-0 items-center gap-1 rounded-full bg-brand px-3 text-xs font-semibold text-on-brand">
                  {c.label}<X className="size-3.5" aria-hidden="true" />
                </button>
              ))}
              <button type="button" onClick={() => setFilters(NO_FILTERS)} className="h-8 shrink-0 px-2 text-xs text-text-muted hover:text-text">پاک کردن همه</button>
            </div>
          ) : null}

          {!results && sections.length > 1 ? (
            <nav aria-label="دسته‌ها" ref={chipsRef} className="no-scrollbar -mx-4 mt-2.5 flex gap-2.5 overflow-x-auto px-4 pt-1 pb-3 lg:hidden">
              {sections.map((s) => {
                const on = active === s.id;

                return (
                  <a key={s.id} href={`#cat-${s.id}`} data-chip={s.id} data-mood={s.mood ?? undefined} aria-current={on ? 'true' : undefined}
                    className="group inline-flex shrink-0 items-center focus-visible:outline-none">
                    <span className={cx(
                      'relative z-10 flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full shadow-[var(--shadow-sm)] ring-2 transition-all duration-[var(--duration-base)] group-hover:scale-105 group-focus-visible:shadow-[var(--focus-ring)]',
                      on ? 'ring-brand' : 'ring-surface',
                    )}><CategoryIcon s={s} /></span>
                    <span className={cx(
                      '-ms-4 flex h-9 items-center gap-1 rounded-e-full ps-6 pe-4 text-sm whitespace-nowrap transition-colors duration-[var(--duration-base)]',
                      on ? 'bg-brand font-semibold text-on-brand shadow-[var(--shadow-sm)]' : 'bg-surface text-text-muted ring-1 ring-border group-hover:text-text',
                    )}>
                      {s.name}
                      {s.mood === 'hot' ? <Flame className={cx('size-3.5', on ? '' : 'text-mood-hot-ink')} aria-label="گرم" /> : null}
                      {s.mood === 'cold' ? <Snowflake className={cx('size-3.5', on ? '' : 'text-mood-cold-ink')} aria-label="سرد" /> : null}
                    </span>
                  </a>
                );
              })}
            </nav>
          ) : <div className="h-3" />}
        </div>

        {results ? (
          <section aria-label="نتیجه‌ی جست‌وجو و فیلتر" className="mt-4">
            <p className="mb-3 text-sm text-text-muted">
              {formatNumber(results.items.length)} مورد{query.trim() ? ` برای «${query.trim()}»` : ''}
              {results.unknownCalories ? <span className="mt-1 block text-xs text-text-subtle">{formatNumber(results.unknownCalories)} محصول کالری ثبت‌شده ندارند و در این نتیجه نیستند.</span> : null}
            </p>
            {results.items.length ? <ul className={LISTS[layout]}>{results.items.map(card)}</ul> : (
              <EmptyState icon={<Search />} title="چیزی پیدا نشد" description="فیلترها را کمتر کنید یا نام دیگری را امتحان کنید." />
            )}
          </section>
        ) : (
          <>
            {featured.length > 0 ? (
              <section aria-labelledby="featured-title" className="mt-5">
                <h2 id="featured-title" className="mb-3 flex items-center gap-2 text-lg font-bold">
                  <span className="flex size-7 items-center justify-center rounded-lg bg-accent-soft text-accent"><Sparkles className="size-4" aria-hidden="true" /></span>
                  پیشنهاد ما
                </h2>
                <ul className="no-scrollbar -mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-3 pt-1 lg:mx-0 lg:px-0">
                  {featured.map((p) => (
                    <li key={p.id} data-mood={p.temperature ?? undefined} className="w-44 shrink-0 snap-start">
                      <button type="button" onClick={() => setOpen(p)} className="relative block w-full rounded-3xl text-start focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none">
                        <ProductPhoto product={p} sizes="tile" className="aspect-[4/5] w-full rounded-3xl" />
                        {hasPhoto(p) ? (
                          // Over a photo: the scrim keeps the text readable.
                          <span className="absolute inset-x-0 bottom-0 flex flex-col gap-1 rounded-b-3xl bg-gradient-to-t from-scrim via-scrim/40 to-transparent p-3 pt-10 text-on-media">
                            <span className="truncate text-sm font-bold">{p.name}</span>
                            <span className="tabular w-fit rounded-full bg-on-media/20 px-2 py-0.5 text-xs font-semibold backdrop-blur-sm">{priceLabel(p)}</span>
                          </span>
                        ) : (
                          <span className="flex flex-col gap-1 px-1 pt-2.5 pb-1">
                            <span className="flex items-center gap-1.5 truncate text-sm font-bold">{p.name}<MoodChip mood={p.temperature} /></span>
                            <span className="tabular text-xs font-semibold text-text-muted">{priceLabel(p)}</span>
                          </span>
                        )}
                      </button>
                    </li>
                  ))}
                </ul>
              </section>
            ) : null}

            {sections.length === 0 ? (
              <EmptyState title="منو هنوز آماده نیست" description="کافه به‌زودی آیتم‌هایش را اینجا می‌گذارد." />
            ) : sections.map((s) => (
              <section key={s.id} id={`cat-${s.id}`} data-menu-section aria-labelledby={`h-${s.id}`} className="mt-8 scroll-mt-40">
                <h2 id={`h-${s.id}`} data-mood={s.mood ?? undefined} className="mb-3 flex items-center gap-3 text-lg font-black">
                  <span aria-hidden="true" className="relative flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl shadow-[var(--shadow-sm)]"><CategoryIcon s={s} /></span>
                  {s.name}
                  <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-text-muted">{formatNumber(s.products.length)} مورد</span>
                  <span aria-hidden="true" className="h-px flex-1 bg-gradient-to-l from-border to-transparent" />
                </h2>
                <ul className={LISTS[layout]}>{s.products.map(card)}</ul>
              </section>
            ))}
          </>
        )}
      </div>

      <div className="sticky top-4 hidden lg:block">
        <CartPanel menu={menu} onOpen={setOpen} />
      </div>

      {sheet ? (
        <FilterSheet key={sheet} open onClose={() => setSheet(0)} value={filters} onApply={setFilters}
          showCalories={calories} tags={tags} maxPrice={maxPrice} count={count} />
      ) : null}

      <Dialog open={open !== null} onClose={() => setOpen(null)} title={open?.name ?? ''} variant="sheet">
        {open ? (
          <ProductDetails product={open} branchId={menu.branch.id} onAdded={() => setOpen(null)} inSheet showCalories={showCalories}
            suggestions={suggestionsFor(menu, [open.id])} onOpenSuggestion={setOpen} />
        ) : null}
      </Dialog>
    </div>
  );
}
