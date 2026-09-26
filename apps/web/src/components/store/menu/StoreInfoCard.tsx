'use client';

import { AtSign, Bike, Clock, MapPin, Navigation, Phone, Store } from 'lucide-react';
import { cx, Ltr } from '@cafe/ui';
import { formatClock } from '@cafe/locale';
import type { StoreBranch, Storefront } from '@/lib/storefront-types';

const ISO_DAY: Record<string, number> = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };

/**
 * The café at a glance, beside the menu on wide screens: open or closed with today's hours, how
 * to get the order, the address with directions, and how to reach them.
 */
export function StoreInfoCard({ store, branch }: { store: Storefront; branch: StoreBranch | undefined }) {
  if (!branch) return null;
  const day = ISO_DAY[new Intl.DateTimeFormat('en-US', { weekday: 'short', timeZone: store.timezone }).format(new Date())];
  const today = branch.opening_hours.filter((h) => h.weekday === day).map((h) => `${formatClock(h.opens_at)} تا ${formatClock(h.closes_at)}`).join('، ');
  const phone = branch.phone ?? store.contact.phone;
  const route = branch.latitude != null && branch.longitude != null ? `https://www.google.com/maps/dir/?api=1&destination=${branch.latitude},${branch.longitude}` : null;
  const row = 'flex items-start gap-2.5 text-sm';
  const link = 'inline-flex h-9 items-center gap-1.5 rounded-xl border border-border px-3 text-xs font-medium transition-colors hover:border-brand hover:text-brand';

  return (
    <section aria-label="اطلاعات کافه" className="flex flex-col gap-3.5 rounded-3xl border border-border bg-surface p-4">
      <p className="flex items-center gap-2 font-bold">
        {branch.name}
        <span className={cx('ms-auto inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold', branch.is_open ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning')}>
          <span aria-hidden="true" className={cx('size-1.5 rounded-full', branch.is_open ? 'bg-success' : 'bg-warning')} />{branch.is_open ? 'باز است' : 'بسته است'}
        </span>
      </p>
      <p className={row}><Clock className="mt-0.5 size-4 shrink-0 text-text-subtle" aria-hidden="true" /><span>امروز {today || 'تعطیل'}</span></p>
      <p className={row}>
        {branch.delivery ? <Bike className="mt-0.5 size-4 shrink-0 text-text-subtle" aria-hidden="true" /> : <Store className="mt-0.5 size-4 shrink-0 text-text-subtle" aria-hidden="true" />}
        <span>{branch.delivery ? 'ارسال با پیک و تحویل در کافه' : 'تحویل در کافه'}</span>
      </p>
      {branch.address ? <p className={row}><MapPin className="mt-0.5 size-4 shrink-0 text-text-subtle" aria-hidden="true" /><span className="leading-6 text-text-muted">{branch.city ? `${branch.city}، ` : ''}{branch.address}</span></p> : null}
      <div className="flex flex-wrap gap-2">
        {route ? <a href={route} target="_blank" rel="noopener noreferrer" className={link}><Navigation className="size-3.5" aria-hidden="true" />مسیریابی</a> : null}
        {phone ? <a href={`tel:${phone}`} className={link}><Phone className="size-3.5" aria-hidden="true" /><Ltr>{phone}</Ltr></a> : null}
        {store.contact.instagram ? <a href={`https://instagram.com/${store.contact.instagram}`} target="_blank" rel="noopener noreferrer" className={link}><AtSign className="size-3.5" aria-hidden="true" /><Ltr>{store.contact.instagram}</Ltr></a> : null}
      </div>
    </section>
  );
}
