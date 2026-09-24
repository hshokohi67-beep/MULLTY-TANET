'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ChevronLeft, ExternalLink, Pause, X } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatNumber } from '@cafe/locale';
import { storyEvent } from '@/app/actions/storefront';
import type { PublicStory } from '@/lib/storefront-types';
import { useStore } from './StoreProvider';

const DURATION_MS = 5000;

function seenKey(tenant: string) {
  return `cs_seen_stories:${tenant}`;
}

function readSeen(tenant: string): string[] {
  try {
    return JSON.parse(localStorage.getItem(seenKey(tenant)) ?? '[]') as string[];
  } catch {
    return [];
  }
}

function writeSeen(tenant: string, ids: string[]) {
  try {
    localStorage.setItem(seenKey(tenant), JSON.stringify(ids.slice(-100)));
  } catch {
    // Private mode or storage disabled: the ring just won't remember.
  }
}

/**
 * Instagram-style story ring above the menu. Unseen stories come first with a brand-coloured
 * ring; seen ones turn grey (remembered on this device only).
 */
export function StoriesBar({ stories, onOpenProduct, onOpenCategory }: {
  stories: PublicStory[];
  onOpenProduct: (slug: string) => void;
  onOpenCategory: (id: string) => void;
}) {
  const { tenant } = useStore();
  const [seen, setSeen] = useState<string[]>([]);
  // The list is frozen when the viewer opens, so marking stories seen can't reshuffle it mid-play.
  const [open, setOpen] = useState<{ list: PublicStory[]; start: number } | null>(null);

  useEffect(() => {
    const t = setTimeout(() => setSeen(readSeen(tenant)), 0);

    return () => clearTimeout(t);
  }, [tenant]);

  // Unseen first, then in the café's order.
  const ordered = useMemo(() => [...stories].sort((a, b) => Number(seen.includes(a.id)) - Number(seen.includes(b.id))), [stories, seen]);

  const markSeen = useCallback((id: string) => {
    setSeen((prev) => {
      if (prev.includes(id)) return prev;
      const next = [...prev, id];
      writeSeen(tenant, next);

      return next;
    });
  }, [tenant]);

  if (stories.length === 0) return null;

  return (
    <>
      <section aria-label="استوری‌ها" className="no-scrollbar -mx-4 flex gap-3 overflow-x-auto px-4 py-1">
        {ordered.map((s, i) => (
          <button key={s.id} type="button" onClick={() => setOpen({ list: ordered, start: i })} className="group flex w-[4.5rem] shrink-0 flex-col items-center gap-1.5">
            <span data-seen={seen.includes(s.id)} className="story-ring rounded-full p-[2.5px] transition-transform group-active:scale-95">
              <span className="block rounded-full bg-bg p-[2px]">
                {/* eslint-disable-next-line @next/next/no-img-element -- tenant media, tiny thumbnail */}
                <img src={s.thumb_url} alt="" width={64} height={64} className="size-16 rounded-full object-cover" />
              </span>
            </span>
            <span className="line-clamp-1 w-full text-center text-[11px] text-text-muted">{s.caption ?? 'استوری'}</span>
          </button>
        ))}
      </section>

      {open !== null ? (
        <StoryViewer
          stories={open.list}
          start={open.start}
          onClose={() => setOpen(null)}
          onSeen={markSeen}
          onAction={(story) => {
            void storyEvent(tenant, story.id, 'click');
            setOpen(null);
            if (story.link?.type === 'product') onOpenProduct(story.link.slug);
            else if (story.link?.type === 'category') onOpenCategory(story.link.category_id);
            else if (story.link?.type === 'url') window.open(story.link.url, '_blank', 'noopener,noreferrer');
          }}
        />
      ) : null}
    </>
  );
}

function StoryViewer({ stories, start, onClose, onSeen, onAction }: {
  stories: PublicStory[];
  start: number;
  onClose: () => void;
  onSeen: (id: string) => void;
  onAction: (story: PublicStory) => void;
}) {
  const { tenant } = useStore();
  const dialog = useRef<HTMLDialogElement>(null);
  const [index, setIndex] = useState(start);
  const [paused, setPaused] = useState(false);
  const [loaded, setLoaded] = useState<string | null>(null);
  const [reduced] = useState(() => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  const press = useRef<{ x: number; y: number; t: number } | null>(null);
  const story = stories[index];

  const next = useCallback(() => setIndex((i) => {
    if (i + 1 >= stories.length) {
      onClose();
      return i;
    }
    return i + 1;
  }), [stories.length, onClose]);
  const prev = useCallback(() => setIndex((i) => Math.max(0, i - 1)), []);

  useEffect(() => {
    const el = dialog.current;
    if (el && !el.open) el.showModal();
  }, []);

  // Count the view, remember it, and warm up the next image.
  useEffect(() => {
    if (!story) return;
    onSeen(story.id);
    void storyEvent(tenant, story.id, 'seen');
    const upcoming = stories[index + 1];
    if (upcoming) new Image().src = upcoming.image_url;
  }, [story, index, stories, tenant, onSeen]);

  // Reduced motion: no animated bar (the global rule would make it end at once), so a timer advances.
  useEffect(() => {
    if (!reduced || paused || loaded !== story?.id) return;
    const t = setTimeout(next, DURATION_MS * 1.6);

    return () => clearTimeout(t);
  }, [reduced, paused, loaded, story, next]);

  if (!story) return null;

  const onKey = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowLeft') { e.preventDefault(); next(); }
    if (e.key === 'ArrowRight') { e.preventDefault(); prev(); }
    if (e.key === ' ') { e.preventDefault(); setPaused((p) => !p); }
  };

  return (
    <dialog ref={dialog} onClose={onClose} onKeyDown={onKey} aria-label={`استوری ${formatNumber(index + 1)} از ${formatNumber(stories.length)}`}
      className="m-0 h-dvh max-h-none w-full max-w-none bg-transparent p-0 backdrop:bg-black/90">
      <div className="dialog-in relative mx-auto flex h-full max-w-md flex-col overflow-hidden bg-black text-white sm:my-4 sm:h-[calc(100%-2rem)] sm:rounded-3xl">
        {/* Progress bars (RTL: they fill from the right, the next story is to the left). */}
        <div className="absolute inset-x-3 top-3 z-20 flex gap-1" aria-hidden="true">
          {stories.map((s, i) => (
            <span key={s.id} className="h-[3px] flex-1 overflow-hidden rounded-full bg-white/30">
              {i < index ? <span className="block h-full w-full bg-white" /> : null}
              {i === index ? (
                reduced ? <span className="block h-full w-full bg-white/80" /> : loaded === s.id ? (
                  <span key={`${s.id}-${index}`} className="story-progress block h-full w-full bg-white"
                    style={{ animationPlayState: paused ? 'paused' : 'running', animationDuration: `${DURATION_MS}ms` }}
                    onAnimationEnd={next} />
                ) : null
              ) : null}
            </span>
          ))}
        </div>

        <div className="absolute inset-x-3 top-7 z-20 flex items-center justify-between">
          {paused ? <span className="glass-light flex items-center gap-1 rounded-full px-2.5 py-1 text-xs"><Pause className="size-3" aria-hidden="true" />مکث</span> : <span />}
          <button type="button" onClick={onClose} aria-label="بستن استوری" className="glass-light flex size-10 items-center justify-center rounded-full">
            <X className="size-5" />
          </button>
        </div>

        {/* Photo. Pointer: tap end half = next, start half = previous, hold = pause, swipe down = close. */}
        <div
          className="relative flex-1 touch-none select-none"
          onPointerDown={(e) => { press.current = { x: e.clientX, y: e.clientY, t: Date.now() }; setPaused(true); }}
          onPointerCancel={() => { press.current = null; setPaused(false); }}
          onPointerUp={(e) => {
            const p = press.current;
            press.current = null;
            setPaused(false);
            if (!p) return;
            if (e.clientY - p.y > 90) { onClose(); return; }
            if (Date.now() - p.t > 300) return; // it was a hold
            const rect = e.currentTarget.getBoundingClientRect();
            // RTL: the right half goes back, the left half goes forward.
            if (e.clientX - rect.left > rect.width / 2) prev(); else next();
          }}
        >
          {/* eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage */}
          <img key={story.id} src={story.image_url} alt={story.caption ?? ''} width={story.width} height={story.height}
            onLoad={() => setLoaded(story.id)} draggable={false}
            className={cx('absolute inset-0 size-full object-cover transition-opacity duration-[var(--duration-base)]', loaded === story.id ? 'opacity-100' : 'opacity-0')} />
          {loaded !== story.id ? <span className="absolute inset-0 m-auto size-8 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" /> : null}
          <div className="pointer-events-none absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-black/85 via-black/40 to-transparent" />
        </div>

        <div className="absolute inset-x-0 bottom-0 z-20 flex flex-col gap-4 px-5 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
          {story.caption ? <p className="text-lg font-bold leading-8 [text-shadow:0_1px_8px_rgb(0_0_0/0.5)]">{story.caption}</p> : null}
          {story.link && story.cta_label ? (
            <button type="button" onClick={() => onAction(story)}
              className="inline-flex h-12 items-center justify-center gap-2 self-stretch rounded-2xl bg-white font-semibold text-black shadow-[var(--shadow-lg)] transition-transform active:scale-[0.98]">
              {story.cta_label}
              {story.link.type === 'url' ? <ExternalLink className="size-4" aria-hidden="true" /> : <ChevronLeft className="size-4" aria-hidden="true" />}
            </button>
          ) : null}
        </div>

        <p className="sr-only" aria-live="polite">استوری {formatNumber(index + 1)} از {formatNumber(stories.length)}. برای رفتن به بعدی کلید چپ و برای قبلی کلید راست را بزنید.</p>
      </div>
    </dialog>
  );
}
