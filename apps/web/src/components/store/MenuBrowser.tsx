'use client';

import { createElement, useCallback, useEffect, useMemo, useRef, useState, useTransition } from 'react';
import { Flame, Plus, Search, Snowflake, Sparkles, X } from 'lucide-react';
import { cx, Dialog, EmptyState } from '@cafe/ui';
import { formatMoney, formatNumber, normalizeForSearch } from '@cafe/locale';
import { addToCart } from '@/app/actions/storefront';
import type { Menu, MenuProduct, Mood, PublicStory } from '@/lib/storefront-types';
import { ProductDetails } from './ProductDetails';
import { hasPhoto, illustrationFor, MoodChip, ProductPhoto } from './ProductVisuals';
import { StoriesBar } from './Stories';
import { useStore } from './StoreProvider';

const OTHER = '__other';

function priceLabel(p: MenuProduct): string {
  return p.variants.length > 1 ? `از ${formatMoney(p.price_from)}` : formatMoney(p.price_from);
}

/** A product with one size and no add-ons can go straight into the cart. */
const quickAddable = (p: MenuProduct) => p.is_available && p.variants.length === 1 && p.modifier_groups.length === 0;

interface Section { id: string; name: string; mood: Mood | null; image: string | null; products: MenuProduct[] }

/**
 * The menu: stories, search, sticky category chips that follow your scroll, a featured row and
 * product cards with a warm/cool mood glow. Cards open a bottom sheet in place (each is still a
 * real link for SEO and "open in new tab").
 */
export function MenuBrowser({ menu, stories }: { menu: Menu; stories: PublicStory[] }) {
  const { tenant, setCart, announce } = useStore();
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState<MenuProduct | null>(null);
  const [active, setActive] = useState<string | null>(null);
  const [adding, startAdd] = useTransition();
  const [justAdded, setJustAdded] = useState<string | null>(null);
  const chipsRef = useRef<HTMLDivElement>(null);

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

  const results = useMemo(() => {
    const q = normalizeForSearch(query.trim());
    if (!q) return null;

    return menu.products.filter((p) => normalizeForSearch(`${p.name} ${p.description ?? ''}`).includes(q));
  }, [query, menu.products]);

  // Scroll-spy: the chip of the section in view is highlighted and kept visible.
  useEffect(() => {
    if (results) return;
    const observer = new IntersectionObserver((entries) => {
      const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
      if (visible) setActive(visible.target.id.replace('cat-', ''));
    }, { rootMargin: '-140px 0px -55% 0px' });
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
    // A parent category may have no section of its own: go to its first child that has one.
    const child = menu.categories.find((c) => c.parent_id === id && document.getElementById(`cat-${c.id}`));
    const target = document.getElementById(`cat-${id}`) ?? (child ? document.getElementById(`cat-${child.id}`) : null);
    setTimeout(() => target?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
  }, [menu.categories]);

  const quickAdd = (p: MenuProduct) => startAdd(async () => {
    const result = await addToCart(tenant, { branchId: menu.branch.id, variantId: p.variants[0].id, quantity: 1, modifierIds: [] });
    if (result.ok) {
      setCart(result.data);
      setJustAdded(p.id);
      setTimeout(() => setJustAdded((id) => (id === p.id ? null : id)), 1200);
      announce(`${p.name} به سبد اضافه شد`);
    } else {
      announce(result.message, 'error');
    }
  });

  const card = (p: MenuProduct) => (
    <li key={p.id} data-mood={p.temperature ?? undefined}
      className={cx('mood-card group relative flex gap-3.5 rounded-3xl border border-border p-3 hover:shadow-[var(--shadow-md)]', !p.is_available && 'opacity-60')}>
      <a href={`/s/${tenant}/p/${encodeURIComponent(p.slug)}`} onClick={(e) => { if (!e.metaKey && !e.ctrlKey) { e.preventDefault(); setOpen(p); } }}
        className="flex min-w-0 flex-1 gap-3.5 after:absolute after:inset-0 after:rounded-3xl focus-visible:outline-none focus-visible:after:shadow-[var(--focus-ring)]">
        <div className="flex min-w-0 flex-1 flex-col py-0.5">
          <h3 className="flex flex-wrap items-center gap-x-2 gap-y-1 font-bold leading-7">
            {p.name}
            <MoodChip mood={p.temperature} />
            {p.is_featured ? <span className="rounded-full bg-accent-soft px-1.5 py-0.5 text-[11px] font-semibold leading-4 text-accent">ویژه</span> : null}
          </h3>
          {p.description ? <p className="mt-0.5 line-clamp-2 text-[13px] leading-6 text-text-muted">{p.description}</p> : null}
          <p className="mt-auto pt-2">
            {p.is_available ? (
              <span className="tabular inline-flex rounded-full bg-surface-muted px-2.5 py-0.5 text-sm font-bold">{priceLabel(p)}</span>
            ) : (
              <span className="inline-flex rounded-full bg-surface-muted px-2.5 py-0.5 text-sm text-text-muted">فعلاً ناموجود</span>
            )}
          </p>
        </div>
        <ProductPhoto product={p} className="size-28 shrink-0 rounded-2xl" />
      </a>
      {p.is_available ? (
        <button type="button" disabled={adding}
          onClick={() => (quickAddable(p) ? quickAdd(p) : setOpen(p))}
          aria-label={quickAddable(p) ? `افزودن ${p.name} به سبد` : `انتخاب گزینه‌های ${p.name}`}
          className={cx(
            'absolute bottom-1.5 end-1.5 z-10 flex size-10 items-center justify-center rounded-full shadow-[var(--shadow-md)] ring-4 ring-surface transition-all duration-[var(--duration-base)] hover:scale-105 active:scale-90 disabled:opacity-70',
            justAdded === p.id ? 'bg-success text-white' : 'bg-brand text-on-brand',
          )}>
          <Plus className={cx('size-5 transition-transform duration-[var(--duration-slow)]', justAdded === p.id && 'rotate-90')} strokeWidth={2.75} aria-hidden="true" />
        </button>
      ) : null}
    </li>
  );

  return (
    <>
      {stories.length ? (
        <div className="mt-4">
          <StoriesBar stories={stories} onOpenProduct={openBySlug} onOpenCategory={openCategory} />
        </div>
      ) : null}

      <div className="glass sticky top-0 z-20 -mx-4 mt-3 rounded-b-3xl px-4 pt-3">
        <label className="relative block">
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
        {!results && sections.length > 1 ? (
          <nav aria-label="دسته‌ها" ref={chipsRef} className="no-scrollbar -mx-4 mt-2.5 flex gap-2.5 overflow-x-auto px-4 pt-1 pb-3">
            {sections.map((s) => {
              const on = active === s.id;

              // A round picture with the name on a pill that sticks out from under it.
              return (
                <a key={s.id} href={`#cat-${s.id}`} data-chip={s.id} data-mood={s.mood ?? undefined} aria-current={on ? 'true' : undefined}
                  className="group inline-flex shrink-0 items-center focus-visible:outline-none">
                  <span className={cx(
                    'relative z-10 flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full shadow-[var(--shadow-sm)] ring-2 transition-all duration-[var(--duration-base)] group-hover:scale-105 group-focus-visible:shadow-[var(--focus-ring)]',
                    on ? 'ring-brand' : 'ring-surface',
                  )}>
                    {s.image ? (
                      // eslint-disable-next-line @next/next/no-img-element -- tenant media, tiny square
                      <img src={s.image} alt="" width={44} height={44} loading="lazy" className="size-full object-cover" />
                    ) : (
                      <span aria-hidden="true" className="photo-fallback flex size-full items-center justify-center">
                        {createElement(illustrationFor(s.name, s.mood), { className: 'size-5', strokeWidth: 1.75 })}
                      </span>
                    )}
                  </span>
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
        <section aria-label="نتیجه‌ی جست‌وجو" className="mt-4">
          <p className="mb-3 text-sm text-text-muted">{formatNumber(results.length)} مورد برای «{query}»</p>
          {results.length ? <ul className="grid gap-3 md:grid-cols-2">{results.map(card)}</ul> : (
            <EmptyState icon={<Search />} title="چیزی پیدا نشد" description="نام دیگری را امتحان کنید یا در دسته‌ها بگردید." />
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
              <ul className="no-scrollbar -mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-3 pt-1">
                {featured.map((p) => (
                  <li key={p.id} data-mood={p.temperature ?? undefined} className="w-44 shrink-0 snap-start">
                    <button type="button" onClick={() => setOpen(p)} className="relative block w-full rounded-3xl text-start focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none">
                      <ProductPhoto product={p} sizes="tile" className="aspect-[4/5] w-full rounded-3xl" />
                      {hasPhoto(p) ? (
                        // Over a photo: a soft shade keeps the text readable.
                        <span className="absolute inset-x-0 bottom-0 flex flex-col gap-1 rounded-b-3xl bg-gradient-to-t from-black/75 via-black/35 to-transparent p-3 pt-10 text-white">
                          <span className="truncate text-sm font-bold">{p.name}</span>
                          <span className="tabular w-fit rounded-full bg-white/20 px-2 py-0.5 text-xs font-semibold backdrop-blur-sm">{priceLabel(p)}</span>
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
            <section key={s.id} id={`cat-${s.id}`} data-menu-section aria-labelledby={`h-${s.id}`} className="mt-8 scroll-mt-36">
              <h2 id={`h-${s.id}`} className="mb-3 flex items-baseline gap-2 text-lg font-bold">
                {s.name}
                <span className="text-xs font-normal text-text-subtle">{formatNumber(s.products.length)} مورد</span>
              </h2>
              <ul className="grid gap-3 md:grid-cols-2">{s.products.map(card)}</ul>
            </section>
          ))}
        </>
      )}

      <Dialog open={open !== null} onClose={() => setOpen(null)} title={open?.name ?? ''} variant="sheet">
        {open ? <ProductDetails product={open} branchId={menu.branch.id} onAdded={() => setOpen(null)} inSheet /> : null}
      </Dialog>
    </>
  );
}
