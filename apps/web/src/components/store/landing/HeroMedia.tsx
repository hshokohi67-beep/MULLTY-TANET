'use client';

import { useSyncExternalStore } from 'react';
import { cx } from '@cafe/ui';

interface NetworkInfo { saveData?: boolean; effectiveType?: string }

const never = () => () => {};

/** Whether this visitor can afford a background video (read once in the browser; false on the server). */
function canPlay(): boolean {
  const net = (navigator as Navigator & { connection?: NetworkInfo }).connection;
  const slow = net?.saveData === true || (net?.effectiveType !== undefined && net.effectiveType !== '4g');

  return !slow && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * The hero's photo, and its short video when the visitor can afford it: never on Save-Data, slow
 * connections or reduced motion. The photo stays underneath as the poster, so a video that can't
 * play (codec, error) simply leaves the photo showing. The server always renders the photo only.
 */
export function HeroMedia({ photo, video, className, drift = true }: { photo: string | null; video: string | null; className?: string; drift?: boolean }) {
  const play = useSyncExternalStore(never, canPlay, () => false);

  return (
    <div aria-hidden="true" className={cx('absolute inset-0 overflow-hidden', className)}>
      {photo ? (
        // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
        <img src={photo} alt="" fetchPriority="high" className={cx('size-full object-cover', drift && 'l-drift')} />
      ) : null}
      {play && video ? (
        <video src={video} poster={photo ?? undefined} autoPlay muted loop playsInline preload="metadata"
          className={cx('absolute inset-0 size-full object-cover', drift && 'l-drift')} />
      ) : null}
    </div>
  );
}
