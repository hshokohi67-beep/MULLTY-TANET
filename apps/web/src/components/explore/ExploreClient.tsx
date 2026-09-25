'use client';

import 'leaflet/dist/leaflet.css';
import Link from 'next/link';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useEffect, useId, useMemo, useRef, useState, useSyncExternalStore, useTransition } from 'react';
import type { Map as LeafletMap } from 'leaflet';
import { Check, Heart, MapPin, Search, Share2, Store, Tag, UtensilsCrossed } from 'lucide-react';
import { cx, Spinner } from '@cafe/ui';
import { toPersianDigits } from '@cafe/locale';
import { suggest } from '@/app/actions/explore';
import type { Place, StoreCard, Suggestions } from '@/lib/marketplace-types';

/* ------------------------------------------------------------------ favourites (this device) */

const FAV_KEY = 'explore.favorites';
const FAV_EVENT = 'explore-favorites';

function readFavorites(): string[] {
  try {
    const v = JSON.parse(localStorage.getItem(FAV_KEY) ?? '[]');
    return Array.isArray(v) ? v.filter((x): x is string => typeof x === 'string').slice(0, 50) : [];
  } catch {
    return [];
  }
}
let cachedRaw: string | null = null;
let cached: string[] = [];
function snapshot(): string[] {
  let raw: string | null = null;
  try { raw = localStorage.getItem(FAV_KEY); } catch { /* storage blocked */ }
  if (raw !== cachedRaw) { cachedRaw = raw; cached = readFavorites(); }

  return cached;
}
const EMPTY: string[] = [];
function subscribe(cb: () => void): () => void {
  window.addEventListener('storage', cb);
  window.addEventListener(FAV_EVENT, cb);

  return () => { window.removeEventListener('storage', cb); window.removeEventListener(FAV_EVENT, cb); };
}
function useFavorites(): string[] {
  return useSyncExternalStore(subscribe, snapshot, () => EMPTY);
}
function toggleFavorite(store: string): void {
  const list = readFavorites();
  const next = list.includes(store) ? list.filter((s) => s !== store) : [store, ...list].slice(0, 50);
  try { localStorage.setItem(FAV_KEY, JSON.stringify(next)); } catch { /* storage blocked: nothing to remember */ }
  window.dispatchEvent(new Event(FAV_EVENT));
}

export function FavoriteButton({ store, name, className }: { store: string; name: string; className?: string }) {
  const on = useFavorites().includes(store);

  return (
    <button type="button" aria-pressed={on} aria-label={on ? `حذف ${name} از علاقه‌مندی‌ها` : `افزودن ${name} به علاقه‌مندی‌ها`}
      onClick={(e) => { e.preventDefault(); e.stopPropagation(); toggleFavorite(store); }}
      className={cx('flex size-9 items-center justify-center rounded-full bg-surface/90 shadow-[var(--shadow-sm)] backdrop-blur-sm transition-transform active:scale-90', className)}>
      <Heart className={cx('size-4', on ? 'fill-danger text-danger' : 'text-text-muted')} aria-hidden="true" />
    </button>
  );
}

/** «علاقه‌مندی‌ها»: the saved cafés, as a normal results page. */
export function FavoritesChip({ active }: { active: boolean }) {
  const favs = useFavorites();
  if (favs.length === 0 && !active) return null;
  const href = favs.length ? `/explore?fav=1&${favs.map((s) => `stores[]=${encodeURIComponent(s)}`).join('&')}` : '/explore';

  return (
    <Link href={active ? '/explore' : href} aria-current={active ? 'true' : undefined}
      className={cx('inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors', active ? 'border-danger bg-danger-soft font-semibold text-danger' : 'border-border bg-surface text-text-muted hover:text-text')}>
      <Heart className={cx('size-4', active && 'fill-danger')} aria-hidden="true" />علاقه‌مندی‌ها<span className="text-xs">{toPersianDigits(favs.length)}</span>
    </Link>
  );
}

export function ShareButton({ title }: { title: string }) {
  const [done, setDone] = useState(false);
  const share = async () => {
    const url = window.location.href;
    try {
      if (navigator.share) await navigator.share({ title, url });
      else { await navigator.clipboard.writeText(url); setDone(true); setTimeout(() => setDone(false), 2000); }
    } catch { /* cancelled */ }
  };

  return (
    <button type="button" onClick={share} className="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-border bg-surface px-4 text-sm font-medium hover:bg-surface-muted">
      {done ? <Check className="size-4 text-success" aria-hidden="true" /> : <Share2 className="size-4" aria-hidden="true" />}{done ? 'نشانی کپی شد' : 'اشتراک‌گذاری'}
    </button>
  );
}

/* ------------------------------------------------------------------ location bar */

const LOC_KEY = 'explore.location';

/** Province → city → area. Changing any of them opens that area's cafés at once; the choice is remembered here. */
export function LocationBar({ places, province, city, district }: { places: Place[]; province: string; city: string; district: string }) {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const [pending, start] = useTransition();

  const go = (loc: { province: string; city: string; district: string }, replace = false) => {
    const next = new URLSearchParams(params.toString());
    for (const k of ['province', 'city', 'district', 'page'] as const) next.delete(k);
    if (loc.province) next.set('province', loc.province);
    if (loc.city) next.set('city', loc.city);
    if (loc.district) next.set('district', loc.district);
    try { localStorage.setItem(LOC_KEY, JSON.stringify(loc)); } catch { /* not remembered */ }
    const url = `${pathname}${next.toString() ? `?${next}` : ''}`;
    start(() => (replace ? router.replace(url) : router.push(url)));
  };

  // First visit without a place in the URL: reopen the last area chosen on this device.
  useEffect(() => {
    if (province || city || district || params.toString() !== '') return;
    try {
      const saved = JSON.parse(localStorage.getItem(LOC_KEY) ?? 'null') as { province?: string; city?: string; district?: string } | null;
      if (saved && (saved.province || saved.city) && places.some((p) => p.province === saved.province)) {
        go({ province: saved.province ?? '', city: saved.city ?? '', district: saved.district ?? '' }, true);
      }
    } catch { /* nothing saved */ }
    // Once, on arrival.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const p = places.find((x) => x.province === province);
  const c = p?.cities.find((x) => x.city === city);
  const select = 'h-10 w-full min-w-0 truncate rounded-xl border border-border bg-surface ps-2.5 pe-2 text-sm font-medium shadow-[var(--shadow-sm)] focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none disabled:opacity-50 sm:w-44';

  return (
    <div className={cx('flex flex-wrap items-center gap-2 transition-opacity', pending && 'opacity-60')} aria-busy={pending}>
      <span className="flex items-center gap-1.5 text-sm font-semibold text-text-muted"><MapPin className="size-4 text-brand" aria-hidden="true" />کجا؟</span>
      <div className="grid w-full grid-cols-3 gap-2 sm:flex sm:w-auto">
      <label className="contents">
        <span className="sr-only">استان</span>
        <select value={province} onChange={(e) => go({ province: e.target.value, city: '', district: '' })} className={select}>
          <option value="">{province ? 'همه‌ی استان‌ها' : 'استان'}</option>
          {places.map((x) => <option key={x.province} value={x.province}>{x.province} ({toPersianDigits(x.count)})</option>)}
        </select>
      </label>
      <label className="contents">
        <span className="sr-only">شهر</span>
        <select value={city} disabled={!p} onChange={(e) => go({ province, city: e.target.value, district: '' })} className={select}>
          <option value="">{city ? 'همه‌ی شهرها' : 'شهر'}</option>
          {p?.cities.map((x) => <option key={x.city} value={x.city}>{x.city} ({toPersianDigits(x.count)})</option>)}
        </select>
      </label>
      <label className="contents">
        <span className="sr-only">محله</span>
        <select value={district} disabled={!c || c.districts.length === 0} onChange={(e) => go({ province, city, district: e.target.value })} className={select}>
          <option value="">{district ? 'همه‌ی محله‌ها' : 'محله'}</option>
          {c?.districts.map((x) => <option key={x.district} value={x.district}>{x.district} ({toPersianDigits(x.count)})</option>)}
        </select>
      </label>
      </div>
      {pending ? <Spinner /> : null}
    </div>
  );
}

/* ------------------------------------------------------------------ search with suggestions */

type Item = { href: string; label: string; hint: string; icon: 'store' | 'place' | 'category' | 'dish' };
const ICONS = { store: Store, place: MapPin, category: Tag, dish: UtensilsCrossed };

export function SearchBox({ q }: { q: string }) {
  const router = useRouter();
  const params = useSearchParams();
  const [text, setText] = useState(q);
  const [open, setOpen] = useState(false);
  const [data, setData] = useState<Suggestions | null>(null);
  const [active, setActive] = useState(-1);
  const [loading, setLoading] = useState(false);
  const listId = useId();
  const seq = useRef(0);

  useEffect(() => {
    const term = text.trim();
    if (term.length < 2) return;
    const mine = ++seq.current;
    const t = setTimeout(async () => {
      setLoading(true);
      const s = await suggest(term);
      if (mine === seq.current) { setData(s); setActive(-1); setLoading(false); }
    }, 220);

    return () => clearTimeout(t);
  }, [text]);

  const withLocation = (extra: Record<string, string>) => {
    const next = new URLSearchParams();
    for (const k of ['province', 'city', 'district']) { const v = params.get(k); if (v) next.set(k, v); }
    Object.entries(extra).forEach(([k, v]) => next.set(k, v));

    return `/explore?${next}`;
  };
  const items: Item[] = useMemo(() => {
    if (!data || text.trim().length < 2) return [];
    return [
      ...data.stores.map((s) => ({ href: `/explore/${s.store}`, label: s.name, hint: s.city, icon: 'store' as const })),
      ...data.places.map((p) => ({ href: `/explore?city=${encodeURIComponent(p.city)}${p.district ? `&district=${encodeURIComponent(p.district)}` : ''}`, label: p.label, hint: 'محل', icon: 'place' as const })),
      ...data.categories.map((c) => ({ href: withLocation({ category: c.key }), label: c.label, hint: 'نوع کافه', icon: 'category' as const })),
      ...data.dishes.map((d) => ({ href: withLocation({ q: d.name }), label: d.name, hint: `در ${toPersianDigits(d.count)} کافه`, icon: 'dish' as const })),
    ];
    // withLocation only reads the current URL
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data, text]);

  const submit = (href?: string) => {
    setOpen(false);
    router.push(href ?? (text.trim() ? withLocation({ q: text.trim() }) : withLocation({})));
  };

  return (
    <form role="search" onSubmit={(e) => { e.preventDefault(); submit(active >= 0 ? items[active]?.href : undefined); }}
      className="relative flex items-center gap-2 rounded-2xl border border-border bg-surface p-2 shadow-[var(--shadow-md)]">
      <label className="relative flex-1">
        <span className="sr-only">نام کافه، غذا، نوشیدنی یا محله</span>
        <Search className="pointer-events-none absolute start-3 top-1/2 size-5 -translate-y-1/2 text-text-subtle" aria-hidden="true" />
        <input value={text} maxLength={80} autoComplete="off" placeholder="کافه، «کاپوچینو»، «صبحانه»، محله…"
          role="combobox" aria-expanded={open && items.length > 0} aria-controls={listId} aria-autocomplete="list"
          aria-activedescendant={active >= 0 ? `${listId}-${active}` : undefined}
          onChange={(e) => { setText(e.target.value); setOpen(true); }}
          onFocus={() => setOpen(true)}
          onBlur={() => setTimeout(() => setOpen(false), 150)}
          onKeyDown={(e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min(items.length - 1, a + 1)); }
            if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(-1, a - 1)); }
            if (e.key === 'Escape') setOpen(false);
          }}
          className="h-12 w-full rounded-xl bg-transparent ps-11 pe-9 text-base focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
        {loading ? <span className="absolute end-3 top-1/2 -translate-y-1/2"><Spinner /></span> : null}
      </label>
      <button type="submit" className="h-12 rounded-xl bg-brand px-5 font-semibold text-on-brand hover:bg-brand-strong sm:px-6">جست‌وجو</button>

      {open && items.length ? (
        <ul id={listId} role="listbox" className="dialog-in absolute inset-x-0 top-full z-40 mt-2 max-h-96 overflow-y-auto rounded-2xl border border-border bg-surface-raised p-1.5 shadow-[var(--shadow-lg)]">
          {items.map((it, i) => {
            const Icon = ICONS[it.icon];

            return (
              <li key={`${it.icon}-${it.href}`} id={`${listId}-${i}`} role="option" aria-selected={i === active}>
                <button type="button" onMouseDown={(e) => e.preventDefault()} onClick={() => submit(it.href)}
                  className={cx('flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-start', i === active ? 'bg-surface-muted' : 'hover:bg-surface-muted')}>
                  <span className="flex size-8 items-center justify-center rounded-lg bg-brand-soft text-brand"><Icon className="size-4" aria-hidden="true" /></span>
                  <span className="min-w-0 flex-1 truncate font-medium">{it.label}</span>
                  <span className="text-xs text-text-muted">{it.hint}</span>
                </button>
              </li>
            );
          })}
        </ul>
      ) : null}
    </form>
  );
}

/* ------------------------------------------------------------------ map of results */

const TILE_URL = process.env.NEXT_PUBLIC_MAP_TILE_URL ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = process.env.NEXT_PUBLIC_MAP_ATTRIBUTION ?? '&copy; OpenStreetMap';

/** Results as pins; each opens a small card (built with text nodes, never HTML strings). */
export function ResultsMap({ stores }: { stores: StoreCard[] }) {
  const box = useRef<HTMLDivElement>(null);
  const map = useRef<LeafletMap | null>(null);
  const located = stores.filter((s) => s.latitude !== null && s.longitude !== null);

  useEffect(() => {
    let cancelled = false;
    void import('leaflet').then((L) => {
      if (cancelled || !box.current) return;
      map.current?.remove();
      const m = L.map(box.current, { center: [35.6997, 51.338], zoom: 11 });
      L.tileLayer(TILE_URL, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(m);
      const markers = located.map((s) => {
        const icon = L.divIcon({ className: cx('map-pin', s.is_featured && 'map-pin-featured'), html: '<span></span>', iconSize: [28, 40], iconAnchor: [14, 38] });
        const card = document.createElement('a');
        card.href = `/explore/${s.store}`;
        card.className = 'block min-w-44 text-start';
        const title = document.createElement('strong');
        title.textContent = s.branch_name ? `${s.name} • ${s.branch_name}` : s.name;
        const meta = document.createElement('span');
        meta.className = 'mt-1 block text-xs';
        meta.textContent = [s.is_open ? 'باز است' : 'بسته', s.district ?? s.city, s.offer].filter(Boolean).join(' • ');
        card.append(title, meta);

        return L.marker([s.latitude as number, s.longitude as number], { icon, title: s.name }).bindPopup(card).addTo(m);
      });
      if (markers.length) m.fitBounds(L.featureGroup(markers).getBounds(), { padding: [32, 32], maxZoom: 15 });
      map.current = m;
    });

    return () => { cancelled = true; map.current?.remove(); map.current = null; };
    // Re-draw when the result set changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [stores]);

  return (
    <div className="flex flex-col gap-2">
      <div ref={box} className="h-[65vh] min-h-96 w-full overflow-hidden rounded-2xl border border-border bg-surface-muted" role="application" aria-label={`نقشه‌ی ${toPersianDigits(located.length)} کافه`} />
      {located.length < stores.length ? <p className="text-xs text-text-muted">{toPersianDigits(stores.length - located.length)} کافه موقعیت روی نقشه ندارند و فقط در فهرست دیده می‌شوند.</p> : null}
    </div>
  );
}
