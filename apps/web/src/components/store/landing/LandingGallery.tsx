'use client';

import { useState, type CSSProperties } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cx, Dialog } from '@cafe/ui';
import { formatNumber } from '@cafe/locale';
import type { LandingMediaItem } from '@/lib/landing-types';

/** The café's photos, as a masonry wall or a swipeable strip; a tap opens the photo large. */
export function LandingGallery({ photos, variant }: { photos: LandingMediaItem[]; variant: 'masonry' | 'strip' }) {
  const [open, setOpen] = useState<number | null>(null);
  const current = open === null ? null : photos[open];
  const step = (by: number) => setOpen((i) => (i === null ? null : (i + by + photos.length) % photos.length));

  return (
    <>
      {variant === 'masonry' ? (
        <div className="columns-2 gap-3 @xl:columns-3 [&>*]:mb-3">
          {photos.map((p, i) => (
            <button key={p.id} type="button" onClick={() => setOpen(i)} data-reveal style={{ '--i': i % 3 } as CSSProperties}
              className="l-card-sm group block w-full overflow-hidden bg-surface-muted focus-visible:outline-2 focus-visible:outline-brand">
              {/* eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage */}
              <img src={p.thumb_url} alt={p.caption ?? ''} width={p.width ?? undefined} height={p.height ?? undefined} loading="lazy" decoding="async"
                className="w-full transition-transform duration-700 group-hover:scale-105" />
            </button>
          ))}
        </div>
      ) : (
        <div className="no-scrollbar -mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-2">
          {photos.map((p, i) => (
            <button key={p.id} type="button" onClick={() => setOpen(i)}
              className="l-card-sm group relative aspect-[4/5] w-64 shrink-0 snap-start overflow-hidden bg-surface-muted focus-visible:outline-2 focus-visible:outline-brand @xl:w-80">
              {/* eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage */}
              <img src={p.thumb_url} alt={p.caption ?? ''} loading="lazy" decoding="async" className="size-full object-cover transition-transform duration-700 group-hover:scale-105" />
              {p.caption ? <span className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-scrim to-transparent p-3 pt-8 text-start text-sm text-on-media">{p.caption}</span> : null}
            </button>
          ))}
        </div>
      )}

      <Dialog open={current !== null} onClose={() => setOpen(null)} size="lg"
        title={current?.caption ?? `عکس ${formatNumber((open ?? 0) + 1)} از ${formatNumber(photos.length)}`}>
        {current ? (
          <div className="flex flex-col gap-3">
            {/* eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage */}
            <img src={current.url} alt={current.caption ?? ''} className="max-h-[70dvh] w-full rounded-xl object-contain" />
            {photos.length > 1 ? (
              <div className="flex items-center justify-between">
                <button type="button" onClick={() => step(-1)} className={cx('inline-flex h-10 items-center gap-1 rounded-xl border border-border px-3 text-sm hover:bg-surface-muted')}>
                  <ChevronRight className="size-4" aria-hidden="true" />قبلی
                </button>
                <span className="text-sm text-text-muted tabular">{formatNumber((open ?? 0) + 1)} / {formatNumber(photos.length)}</span>
                <button type="button" onClick={() => step(1)} className="inline-flex h-10 items-center gap-1 rounded-xl border border-border px-3 text-sm hover:bg-surface-muted">
                  بعدی<ChevronLeft className="size-4" aria-hidden="true" />
                </button>
              </div>
            ) : null}
          </div>
        ) : null}
      </Dialog>
    </>
  );
}
