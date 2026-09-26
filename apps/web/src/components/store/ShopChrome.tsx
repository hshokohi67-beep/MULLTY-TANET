import Link from 'next/link';
import type { ReactNode } from 'react';
import { AtSign, Clock, MapPin, Navigation, Phone, UserRound } from 'lucide-react';
import { cx, Ltr } from '@cafe/ui';
import { formatClock } from '@cafe/locale';
import { initialOf } from '@/components/explore/Art';
import { TableBar } from '@/components/store/TableBar';
import type { Storefront } from '@/lib/storefront-types';

/**
 * The shop frame around the menu, product, cart and account pages: a light header, table mode,
 * the page, and a footer with hours and contact. (The landing page has its own full-bleed frame.)
 */
export function ShopChrome({ tenant, store, children }: { tenant: string; store: Storefront; children: ReactNode }) {
  const branch = store.branches[0];
  const today = ({ Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 } as Record<string, number>)[new Intl.DateTimeFormat('en-US', { weekday: 'short', timeZone: store.timezone }).format(new Date())];

  return (
    <>
      <header className="border-b border-border bg-surface">
        <div className="mx-auto flex h-16 max-w-5xl items-center gap-3 px-4">
          <Link href={`/s/${tenant}`} className="flex min-w-0 flex-1 items-center gap-3">
            {store.branding?.logo_url ? (
              // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
              <img src={store.branding.logo_url} alt="" className="size-10 rounded-xl object-contain" />
            ) : (
              <span aria-hidden="true" className="flex size-10 items-center justify-center rounded-xl bg-brand text-lg font-black text-on-brand">{initialOf(store.name)}</span>
            )}
            <span className="truncate text-lg font-bold">{store.name}</span>
          </Link>
          <Link href={`/s/${tenant}/account`} aria-label="حساب کاربری" className="flex size-10 items-center justify-center rounded-full bg-surface-muted text-text-muted hover:text-text">
            <UserRound className="size-5" />
          </Link>
        </div>
      </header>
      <TableBar />

      <main id="main" className="page-in mx-auto w-full max-w-5xl flex-1 px-4 pb-28">{children}</main>

      <footer className="mt-10 border-t border-border bg-surface">
        <div className="mx-auto grid max-w-5xl gap-6 px-4 py-8 pb-32 sm:grid-cols-[1.2fr_1fr]">
          <div className="flex flex-col gap-3">
            <div className="flex items-center gap-3">
              {store.branding?.logo_url ? (
                // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
                <img src={store.branding.logo_url} alt="" className="size-12 rounded-2xl object-contain" />
              ) : <span aria-hidden="true" className="flex size-12 items-center justify-center rounded-2xl bg-brand text-xl font-black text-on-brand">{initialOf(store.name)}</span>}
              <div className="min-w-0">
                <p className="truncate font-black">{store.name}</p>
                {branch?.address ? <p className="flex items-start gap-1 text-sm text-text-muted"><MapPin className="mt-1 size-3.5 shrink-0" aria-hidden="true" />{branch.city ? `${branch.city}، ` : ''}{branch.address}</p> : null}
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              {store.contact.phone ? (
                <a href={`tel:${store.contact.phone}`} className="inline-flex h-10 items-center gap-2 rounded-xl border border-border px-3 text-sm hover:bg-surface-muted"><Phone className="size-4 text-brand" aria-hidden="true" /><Ltr>{store.contact.phone}</Ltr></a>
              ) : null}
              {store.contact.instagram ? (
                <a href={`https://instagram.com/${store.contact.instagram}`} rel="noopener noreferrer" target="_blank" className="inline-flex h-10 items-center gap-2 rounded-xl border border-border px-3 text-sm hover:bg-surface-muted"><AtSign className="size-4 text-brand" aria-hidden="true" /><Ltr>{store.contact.instagram}</Ltr></a>
              ) : null}
              {branch?.latitude != null && branch.longitude != null ? (
                <a href={`https://www.google.com/maps/dir/?api=1&destination=${branch.latitude},${branch.longitude}`} rel="noopener noreferrer" target="_blank" className="inline-flex h-10 items-center gap-2 rounded-xl border border-border px-3 text-sm hover:bg-surface-muted"><Navigation className="size-4 text-brand" aria-hidden="true" />مسیریابی</a>
              ) : null}
            </div>
          </div>
          {branch && branch.opening_hours.length > 0 ? (
            <section aria-labelledby="hours" className="rounded-2xl bg-surface-muted p-4 text-sm">
              <h2 id="hours" className="mb-2 flex items-center gap-2 font-bold"><Clock className="size-4 text-brand" aria-hidden="true" />ساعت کاری</h2>
              <ul className="flex flex-col gap-0.5">
                {branch.opening_hours.map((h, i) => (
                  <li key={i} className={cx('flex justify-between gap-3 rounded-lg px-2 py-1', h.weekday === today && 'bg-surface font-semibold text-brand shadow-[var(--shadow-sm)]')}>
                    <span>{h.weekday_label}{h.weekday === today ? ' (امروز)' : ''}</span><span className="tabular">{formatClock(h.opens_at)} تا {formatClock(h.closes_at)}</span>
                  </li>
                ))}
              </ul>
            </section>
          ) : null}
        </div>
        <p className="border-t border-border py-3 text-center text-xs text-text-subtle">ساخته‌شده با کافه‌یار</p>
      </footer>
    </>
  );
}
