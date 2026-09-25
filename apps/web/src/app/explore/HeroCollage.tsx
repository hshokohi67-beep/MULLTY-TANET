import { BadgePercent } from 'lucide-react';
import { cx } from '@cafe/ui';
import { CoverArt, Logo } from '@/components/explore/Art';
import type { StoreCard } from '@/lib/marketplace-types';

const SPOTS = [
  'end-0 top-2 w-[60%] rotate-[4deg] z-10',
  'start-0 top-20 w-[56%] -rotate-[5deg] z-20 [animation-delay:-2s]',
  'end-12 bottom-0 w-[52%] -rotate-1 z-30 [animation-delay:-4s]',
];

/** Decorative stack of real cafés beside the hero (desktop only; the same cafés are linked below). */
export function HeroCollage({ stores }: { stores: StoreCard[] }) {
  if (stores.length === 0) return null;
  const offer = stores.find((s) => s.offer)?.offer;

  return (
    <div aria-hidden="true" className="relative hidden h-[420px] lg:block">
      <div className="absolute inset-10 rounded-full bg-brand-soft blur-3xl" />
      {stores.map((s, i) => (
        <div key={s.store} data-brand={s.store} className={cx('float-y absolute overflow-hidden rounded-3xl border-4 border-surface bg-surface shadow-[var(--shadow-lg)]', SPOTS[i])}>
          <div className="aspect-[4/3] overflow-hidden">
            {s.image_url
              // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
              ? <img src={s.image_url} alt="" className="size-full object-cover" />
              : <CoverArt store={s.store} name={s.name} category={s.categories[0]?.key} />}
          </div>
          <div className="flex items-center gap-2.5 p-3">
            <Logo url={s.logo_url} name={s.name} className="size-9 shrink-0 rounded-xl text-base" />
            <div className="min-w-0">
              <p className="truncate text-sm font-bold">{s.name}</p>
              <p className="truncate text-xs text-text-muted">{s.district ? `${s.district}، ${s.city}` : s.city}</p>
            </div>
            <span className={cx('ms-auto size-2 shrink-0 rounded-full', s.is_open ? 'bg-success' : 'bg-text-subtle')} />
          </div>
        </div>
      ))}
      {offer ? (
        <span className="float-y absolute start-8 top-2 z-40 inline-flex max-w-[60%] items-center gap-1.5 rounded-2xl bg-danger px-3 py-2 text-sm font-bold text-on-danger shadow-[var(--shadow-lg)] [animation-delay:-3s]">
          <BadgePercent className="size-4 shrink-0" /><span className="truncate">{offer}</span>
        </span>
      ) : null}
    </div>
  );
}
