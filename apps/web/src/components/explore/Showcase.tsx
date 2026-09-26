'use client';

import Link from 'next/link';
import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { ArrowLeft, ChevronLeft, ChevronRight, Megaphone, Store } from 'lucide-react';
import { cx } from '@cafe/ui';
import { trackAd } from '@/app/actions/explore';
import type { Banner } from '@/lib/marketplace-types';
import { storeHref } from '@/lib/store-links';
import { CoverArt, HeroPattern, Logo } from './Art';

/* ------------------------------------------------------------------ ad beacons */

const seen = new Set<string>();

/** Counts an impression once the element is at least half visible for a second (once per page view). */
function useImpression(token: string | undefined) {
  const ref = useRef<HTMLElement | null>(null);
  useEffect(() => {
    const el = ref.current;
    if (!token || !el || seen.has(token) || typeof IntersectionObserver === 'undefined') return;
    let timer: ReturnType<typeof setTimeout> | undefined;
    const io = new IntersectionObserver(([entry]) => {
      if (entry?.isIntersecting && document.visibilityState === 'visible') {
        timer = setTimeout(() => {
          if (seen.has(token)) return;
          seen.add(token);
          void trackAd(token, 'impression');
          io.disconnect();
        }, 1000);
      } else if (timer) {
        clearTimeout(timer);
      }
    }, { threshold: 0.5 });
    io.observe(el);

    return () => { io.disconnect(); if (timer) clearTimeout(timer); };
  }, [token]);

  return ref;
}

/** Wraps a sponsored result: counts its impression and any click inside it. */
export function Tracked({ token, children, className }: { token: string; children: ReactNode; className?: string }) {
  const ref = useImpression(token);

  return (
    <div ref={(el) => { ref.current = el; }} className={className} onClickCapture={() => void trackAd(token, 'click')}>
      {children}
    </div>
  );
}

/* ------------------------------------------------------------------ banner carousel */

function BannerSlide({ b, index, total, eager }: { b: Banner; index: number; total: number; eager: boolean }) {
  const ref = useImpression(b.token);

  return (
    <article ref={(el) => { ref.current = el; }} data-brand={b.store} aria-roledescription="اسلاید" aria-label={`${index + 1} از ${total}`}
      className="relative h-full w-full shrink-0 snap-center overflow-hidden">
      {b.image_url ? (
        <picture>
          {b.image_small_url ? <source media="(max-width: 640px)" srcSet={b.image_small_url} /> : null}
          <img src={b.image_url} alt="" loading={eager ? 'eager' : 'lazy'} className="absolute inset-0 size-full object-cover" />
        </picture>
      ) : <div className="absolute inset-0"><CoverArt store={b.store} name={b.name} size="lg" /></div>}
      <div className="absolute inset-0 bg-gradient-to-t from-scrim via-scrim/45 to-transparent sm:bg-gradient-to-l sm:via-scrim/35" aria-hidden="true" />
      <div className="relative flex h-full flex-col justify-end gap-2.5 p-5 text-on-media sm:max-w-[62%] sm:justify-center sm:gap-3 sm:p-10">
        <span className="glass-light inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold"><Megaphone className="size-3" aria-hidden="true" />تبلیغ</span>
        <span className="flex items-center gap-2.5">
          <Logo url={b.logo_url} name={b.name} className="size-9 shrink-0 rounded-xl text-base ring-2 ring-on-media/40" />
          <span className="font-semibold">{b.name}</span>
          <span className="text-sm text-on-media-muted">• {b.city}</span>
        </span>
        <h3 className="text-2xl font-black leading-tight [text-wrap:balance] sm:text-4xl">{b.headline}</h3>
        {b.body ? <p className="line-clamp-2 text-sm text-on-media-muted sm:text-lg">{b.body}</p> : null}
        <span className="mt-1 inline-flex h-11 w-fit items-center gap-2 rounded-xl bg-brand px-5 text-sm font-bold text-on-brand shadow-[var(--shadow-lg)]">{b.cta_label}<ArrowLeft className="size-4" aria-hidden="true" /></span>
      </div>
      <Link href={storeHref(b.href)} onClick={() => void trackAd(b.token, 'click')} aria-label={`${b.name}: ${b.headline} (تبلیغ)`}
        className="absolute inset-0 rounded-3xl focus-visible:shadow-[inset_0_0_0_3px_var(--color-on-media)] focus-visible:outline-none" />
    </article>
  );
}

/** The platform's own slide: invites café owners (shown when there are few paid banners). */
function HouseSlide() {
  return (
    <article aria-roledescription="اسلاید" className="relative h-full w-full shrink-0 snap-center overflow-hidden bg-brand-strong text-on-brand">
      <div className="absolute inset-0 bg-gradient-to-br from-brand to-brand-strong" aria-hidden="true" />
      <HeroPattern className="text-on-brand opacity-[0.12]" />
      <div className="absolute -end-16 -top-20 size-80 rounded-full bg-on-brand/10 blur-3xl" aria-hidden="true" />
      <div className="relative flex h-full flex-col justify-end gap-3 p-5 sm:max-w-[62%] sm:justify-center sm:p-10">
        <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-on-brand/15 px-2.5 py-1 text-[11px] font-semibold"><Store className="size-3" aria-hidden="true" />برای کسب‌وکارها</span>
        <h3 className="text-2xl font-black leading-tight [text-wrap:balance] sm:text-4xl">کسب‌وکارتان را به مشتری‌های تازه معرفی کنید</h3>
        <p className="text-sm opacity-85 sm:text-lg">منوی آنلاین، سفارش بدون واسطه و جایی در خوراک‌گردی؛ همه از یک پنل.</p>
        <Link href="/login" className="mt-1 inline-flex h-11 w-fit items-center gap-2 rounded-xl bg-on-brand px-5 text-sm font-bold text-brand-strong shadow-[var(--shadow-lg)] hover:opacity-90">
          ورود به پنل کافه‌یار<ArrowLeft className="size-4" aria-hidden="true" />
        </Link>
      </div>
    </article>
  );
}

/**
 * Paid banners (and the house slide) as a swipeable carousel: native scroll-snap for touch,
 * arrows and dots for mouse and keyboard. Auto-advances only while visible, not hovered or
 * focused, and never with reduced motion.
 */
export function BannerCarousel({ banners, house = banners.length < 2 }: { banners: Banner[]; house?: boolean }) {
  const track = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState(0);
  const [paused, setPaused] = useState(false);
  const total = banners.length + (house ? 1 : 0);

  const go = useCallback((i: number) => {
    const el = track.current;
    const slides = el ? Array.from(el.children) as HTMLElement[] : [];
    const first = slides[0];
    const target = slides[(i + total) % total];
    if (!el || !first || !target) return;
    // Works for RTL too: the offset from the first slide is negative, like RTL scrollLeft.
    el.scrollTo({ left: target.offsetLeft - first.offsetLeft, behavior: 'smooth' });
  }, [total]);

  useEffect(() => {
    const el = track.current;
    if (!el) return;
    const onScroll = () => setActive(Math.round(Math.abs(el.scrollLeft) / Math.max(1, el.clientWidth)));
    el.addEventListener('scroll', onScroll, { passive: true });

    return () => el.removeEventListener('scroll', onScroll);
  }, []);

  useEffect(() => {
    const el = track.current;
    if (!el || total < 2 || paused || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    let visible = false;
    const io = new IntersectionObserver(([e]) => { visible = !!e?.isIntersecting; }, { threshold: 0.6 });
    io.observe(el);
    const t = setInterval(() => { if (visible && document.visibilityState === 'visible') go(active + 1); }, 6500);

    return () => { clearInterval(t); io.disconnect(); };
  }, [active, paused, total, go]);

  if (total === 0) return null;
  // Arrows sit in the far bottom corner, away from the text (which starts at the start edge).
  const arrow = 'glass-light absolute bottom-3 z-10 hidden size-10 items-center justify-center rounded-full text-on-media transition-opacity hover:opacity-90 sm:flex';

  return (
    <section aria-roledescription="carousel" aria-label="پیشنهادهای ویژه" className="relative"
      onMouseEnter={() => setPaused(true)} onMouseLeave={() => setPaused(false)} onFocusCapture={() => setPaused(true)} onBlurCapture={() => setPaused(false)}>
      <div ref={track} className="relative flex h-[clamp(240px,62vw,380px)] snap-x snap-mandatory overflow-x-auto overflow-y-hidden rounded-3xl bg-surface-muted shadow-[var(--shadow-lg)] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {banners.map((b, i) => <BannerSlide key={b.token} b={b} index={i} total={total} eager={i === 0} />)}
        {house ? <HouseSlide /> : null}
      </div>
      {total > 1 ? (
        <>
          <button type="button" onClick={() => go(active - 1)} className={cx(arrow, 'end-16')} aria-label="اسلاید قبل"><ChevronRight className="size-5" aria-hidden="true" /></button>
          <button type="button" onClick={() => go(active + 1)} className={cx(arrow, 'end-4')} aria-label="اسلاید بعد"><ChevronLeft className="size-5" aria-hidden="true" /></button>
          <div className="absolute inset-x-0 bottom-3 flex justify-center gap-1.5" role="group" aria-label="اسلایدها">
            {Array.from({ length: total }, (_, i) => (
              <button key={i} type="button" onClick={() => go(i)} aria-label={`اسلاید ${i + 1}`} aria-current={i === active ? 'true' : undefined}
                className={cx('h-1.5 rounded-full bg-on-media transition-all', i === active ? 'w-6 opacity-100' : 'w-1.5 opacity-50 hover:opacity-80')} />
            ))}
          </div>
        </>
      ) : null}
    </section>
  );
}

/* ------------------------------------------------------------------ horizontal rail */

/** A horizontal, snap-scrolling row with desktop arrows that appear only when there is more to see. */
export function Rail({ children, label, itemClass = 'w-[80%] sm:w-[calc((100%-2rem)/3)] lg:w-[calc((100%-3rem)/4)]' }: { children: ReactNode[]; label: string; itemClass?: string }) {
  const track = useRef<HTMLDivElement>(null);
  const [edges, setEdges] = useState({ start: true, end: false });

  useEffect(() => {
    const el = track.current;
    if (!el) return;
    const update = () => {
      const max = el.scrollWidth - el.clientWidth;
      const pos = Math.abs(el.scrollLeft);
      setEdges({ start: pos < 4, end: pos > max - 4 });
    };
    update();
    el.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);

    return () => { el.removeEventListener('scroll', update); window.removeEventListener('resize', update); };
  }, []);

  const scroll = (dir: 1 | -1) => {
    const el = track.current;
    if (!el) return;
    const rtl = getComputedStyle(el).direction === 'rtl';
    el.scrollBy({ left: dir * (rtl ? -1 : 1) * el.clientWidth * 0.85, behavior: 'smooth' });
  };
  const arrow = 'absolute top-[38%] z-10 hidden size-10 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-surface-raised text-text shadow-[var(--shadow-md)] transition-opacity hover:bg-surface-muted lg:flex';

  return (
    <div className="relative">
      <div ref={track} role="list" aria-label={label} className="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-px-4 px-4 pb-3 pt-1 [scrollbar-width:none] sm:mx-0 sm:scroll-px-0 sm:px-0 [&::-webkit-scrollbar]:hidden">
        {children.map((child, i) => <div key={i} role="listitem" className={cx('flex shrink-0 snap-start', itemClass)}>{child}</div>)}
      </div>
      {!edges.start ? <button type="button" onClick={() => scroll(-1)} className={cx(arrow, '-start-5')} aria-label="قبلی"><ChevronRight className="size-5" aria-hidden="true" /></button> : null}
      {!edges.end ? <button type="button" onClick={() => scroll(1)} className={cx(arrow, '-end-5')} aria-label="بعدی"><ChevronLeft className="size-5" aria-hidden="true" /></button> : null}
    </div>
  );
}
