'use client';

import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useState, useTransition } from 'react';
import { Clock, LocateFixed, MapPin, Search } from 'lucide-react';
import { cx, Spinner } from '@cafe/ui';
import { AMENITY_ICONS } from '@/components/explore/ExploreParts';
import type { Labelled } from '@/lib/marketplace-types';

/** Big search box: text + city, submitted as a normal GET form (works without JavaScript too). */
export function ExploreSearch({ q, city, cities }: { q: string; city: string; cities: { city: string; count: number }[] }) {
  return (
    <form action="/explore" method="get" role="search" className="flex flex-col gap-2 rounded-2xl border border-border bg-surface p-2 shadow-[var(--shadow-md)] sm:flex-row sm:items-center">
      <label className="relative flex-1">
        <span className="sr-only">نام کافه، غذا یا نوشیدنی</span>
        <Search className="pointer-events-none absolute start-3 top-1/2 size-5 -translate-y-1/2 text-text-subtle" aria-hidden="true" />
        <input name="q" defaultValue={q} maxLength={80} placeholder="نام کافه، «کاپوچینو»، «صبحانه»…" autoComplete="off"
          className="h-12 w-full rounded-xl bg-transparent ps-11 pe-3 text-base focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
      </label>
      <label className="relative sm:w-48">
        <span className="sr-only">شهر</span>
        <MapPin className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-text-subtle" aria-hidden="true" />
        <select name="city" defaultValue={city} className="h-12 w-full appearance-none rounded-xl bg-surface-muted ps-9 pe-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none">
          <option value="">همه‌ی شهرها</option>
          {cities.map((c) => <option key={c.city} value={c.city}>{c.city}</option>)}
        </select>
      </label>
      <button type="submit" className="h-12 rounded-xl bg-brand px-6 font-semibold text-on-brand hover:bg-brand-strong">جست‌وجو</button>
    </form>
  );
}

/** Amenities, price, open-now and sort; each change updates the URL (server renders the results). */
export function ExploreFilters({ amenities, selected, price, openNow, sort }: { amenities: Labelled[]; selected: string[]; price: string; openNow: boolean; sort: string }) {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const [pending, start] = useTransition();
  const [locating, setLocating] = useState(false);

  const go = (mutate: (p: URLSearchParams) => void) => {
    const next = new URLSearchParams(params.toString());
    mutate(next);
    next.delete('page');
    start(() => router.push(`${pathname}?${next.toString()}`));
  };
  const toggleAmenity = (key: string) => go((p) => {
    const list = p.getAll('amenities[]');
    p.delete('amenities[]');
    (list.includes(key) ? list.filter((k) => k !== key) : [...list, key]).forEach((k) => p.append('amenities[]', k));
  });
  const nearMe = () => {
    if (!navigator.geolocation) return;
    setLocating(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => { setLocating(false); go((p) => { p.set('lat', pos.coords.latitude.toFixed(4)); p.set('lng', pos.coords.longitude.toFixed(4)); p.set('sort', 'nearest'); }); },
      () => setLocating(false),
      { timeout: 8000, maximumAge: 300_000 },
    );
  };
  const chip = (active: boolean) => cx('inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors', active ? 'border-brand bg-brand-soft font-semibold text-brand-strong' : 'border-border bg-surface text-text-muted hover:text-text');

  return (
    <div className={cx('flex flex-col gap-3 transition-opacity', pending && 'opacity-60')} aria-busy={pending}>
      <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
        <button type="button" onClick={() => go((p) => (openNow ? p.delete('open_now') : p.set('open_now', '1')))} aria-pressed={openNow} className={chip(openNow)}>
          <Clock className="size-4" aria-hidden="true" />الان باز است
        </button>
        <button type="button" onClick={nearMe} aria-pressed={sort === 'nearest'} className={chip(sort === 'nearest')}>
          {locating ? <Spinner /> : <LocateFixed className="size-4" aria-hidden="true" />}نزدیک من
        </button>
        {amenities.map((a) => {
          const Icon = AMENITY_ICONS[a.key];
          const on = selected.includes(a.key);

          return (
            <button key={a.key} type="button" onClick={() => toggleAmenity(a.key)} aria-pressed={on} className={chip(on)}>
              {Icon ? <Icon className="size-4" aria-hidden="true" /> : null}{a.label}
            </button>
          );
        })}
      </div>
      <div className="flex flex-wrap items-center gap-3 text-sm">
        <label className="flex items-center gap-2">
          <span className="text-text-muted">قیمت</span>
          <select value={price} onChange={(e) => go((p) => (e.target.value ? p.set('price', e.target.value) : p.delete('price')))} className="h-9 rounded-lg border border-border bg-surface px-2">
            <option value="">همه</option><option value="1">اقتصادی</option><option value="2">متوسط</option><option value="3">بالا</option><option value="4">لوکس</option>
          </select>
        </label>
        <label className="flex items-center gap-2">
          <span className="text-text-muted">مرتب‌سازی</span>
          <select value={sort} onChange={(e) => go((p) => (e.target.value ? p.set('sort', e.target.value) : p.delete('sort')))} className="h-9 rounded-lg border border-border bg-surface px-2">
            <option value="">پیشنهادی</option><option value="newest">تازه‌ترین</option>
            {params.get('lat') ? <option value="nearest">نزدیک‌ترین</option> : null}
          </select>
        </label>
      </div>
    </div>
  );
}
