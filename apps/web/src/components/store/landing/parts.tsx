import Link from 'next/link';
import { createElement, type CSSProperties, type ReactNode } from 'react';
import { CakeSlice, Coffee, Croissant, CupSoda, IceCreamCone, Leaf, Sandwich, type LucideIcon } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatClock } from '@cafe/locale';
import type { LandingDesign } from '@/lib/landing-types';
import type { StoreBranch } from '@/lib/storefront-types';

/** A link on the live page; plain text in the panel's preview (so the editor never navigates away). */
export function Go({ href, preview, className, children, external = false }: { href: string; preview?: boolean; className?: string; children: ReactNode; external?: boolean }) {
  if (preview) return <span className={className}>{children}</span>;
  if (external) return href.startsWith('http') ? <a href={href} target="_blank" rel="noopener noreferrer" className={className}>{children}</a> : <a href={href} className={className}>{children}</a>;

  return <Link href={href} className={className}>{children}</Link>;
}

/** `--i` for staggered reveals. */
export const nth = (i: number) => ({ '--i': i }) as CSSProperties;

/** "Section heading": an eyebrow line and a display title. */
export function Heading({ eyebrow, title, center = false, className }: { eyebrow?: string | null; title: string | null; center?: boolean; className?: string }) {
  if (!title && !eyebrow) return null;

  return (
    <div data-reveal className={cx('mb-10 flex flex-col gap-3 @xl:mb-14', center && 'items-center text-center', className)}>
      {eyebrow ? <span className="l-eyebrow text-text-subtle">{eyebrow}</span> : null}
      {title ? <h2 className="l-display text-3xl @xl:text-5xl">{title}</h2> : null}
    </div>
  );
}

const ART: LucideIcon[] = [Coffee, Croissant, CakeSlice, CupSoda, IceCreamCone, Leaf, Sandwich];
const SPOTS: [number, number, number][] = [[6, 14, -14], [22, 78, 18], [41, 30, -8], [63, 86, 24], [78, 18, 12], [92, 62, -20], [52, 58, 6]];

/** Faint food drawings behind a section ("نقش خوراکی" texture). */
export function ArtLayer({ design, seed = 0 }: { design: LandingDesign; seed?: number }) {
  if (design.texture !== 'art') return null;

  return (
    <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10 overflow-hidden text-text opacity-[0.07]">
      {SPOTS.map(([x, y, r], i) => createElement(ART[(i + seed) % ART.length], {
        key: i, className: 'absolute size-10 @xl:size-14', strokeWidth: 1.3,
        style: { insetInlineStart: `${x}%`, top: `${y}%`, transform: `translate(-50%, -50%) rotate(${r + seed * 7}deg)` },
      }))}
    </div>
  );
}

const ISO_DAY: Record<string, number> = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };

/** Today's ISO weekday in the café's time zone. */
export function todayIn(timezone: string): number {
  return ISO_DAY[new Intl.DateTimeFormat('en-US', { weekday: 'short', timeZone: timezone }).format(new Date())] ?? 1;
}

/** "۸:۰۰ تا ۲۳:۰۰" for today, or null when closed all day. */
export function hoursToday(branch: StoreBranch, timezone: string): string | null {
  const today = branch.opening_hours.filter((h) => h.weekday === todayIn(timezone));

  return today.length ? today.map((h) => `${formatClock(h.opens_at)} تا ${formatClock(h.closes_at)}`).join('، ') : null;
}

export function mapsHref(branch: StoreBranch): string | null {
  return branch.latitude != null && branch.longitude != null ? `https://www.google.com/maps/dir/?api=1&destination=${branch.latitude},${branch.longitude}` : null;
}
