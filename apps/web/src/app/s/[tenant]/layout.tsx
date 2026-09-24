import type { Metadata, Viewport } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { AtSign, Phone, UserRound } from 'lucide-react';
import { brandCss, brandTokens, Ltr } from '@cafe/ui';
import { formatClock } from '@cafe/locale';
import { CartBar } from '@/components/store/CartBar';
import { StoreProvider } from '@/components/store/StoreProvider';
import { TableBar } from '@/components/store/TableBar';
import { getStorefront, storeUrl } from '@/lib/storefront';

export async function generateMetadata({ params }: LayoutProps<'/s/[tenant]'>): Promise<Metadata> {
  const { tenant } = await params;
  const store = await getStorefront(tenant);
  if (!store) return {};

  const description = store.branding?.seo_description ?? `منوی آنلاین و سفارش از ${store.name}`;

  return {
    title: { default: store.branding?.seo_title ?? store.name, template: `%s | ${store.name}` },
    description,
    metadataBase: new URL(storeUrl(tenant)),
    manifest: `/s/${tenant}/manifest.webmanifest`,
    robots: { index: true, follow: true },
    icons: store.branding?.logo_url ? { icon: store.branding.logo_url, apple: store.branding.logo_url } : undefined,
    appleWebApp: { capable: true, title: store.name, statusBarStyle: 'default' },
    openGraph: {
      siteName: store.name,
      locale: 'fa_IR',
      type: 'website',
      ...(store.branding?.logo_url ? { images: [{ url: store.branding.logo_url }] } : {}),
    },
  };
}

export async function generateViewport({ params }: LayoutProps<'/s/[tenant]'>): Promise<Viewport> {
  const store = await getStorefront((await params).tenant);

  return { themeColor: brandTokens(store?.branding?.primary_color)?.brand ?? '#0e7c6b', viewportFit: 'cover' };
}

/**
 * The storefront shell of one café: its brand colour (made readable in both themes), a light
 * header, table mode, the cart bar and a footer with hours and contact. Rendered from cached
 * public data only; everything personal loads in the client islands.
 */
export default async function StoreLayout({ children, params }: LayoutProps<'/s/[tenant]'>) {
  const { tenant } = await params;
  const store = await getStorefront(tenant);
  if (!store) notFound();

  const css = brandCss(store.branding?.primary_color, '.store');
  const branch = store.branches[0];

  return (
    <div className="store flex min-h-dvh flex-col">
      {css ? <style dangerouslySetInnerHTML={{ __html: css }} /> : null}
      <StoreProvider tenant={tenant} store={store}>
        <header className="border-b border-border bg-surface">
          <div className="mx-auto flex h-16 max-w-5xl items-center gap-3 px-4">
            <Link href={`/s/${tenant}`} className="flex min-w-0 flex-1 items-center gap-3">
              {store.branding?.logo_url ? (
                // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
                <img src={store.branding.logo_url} alt="" className="size-10 rounded-xl object-contain" />
              ) : (
                <span aria-hidden="true" className="flex size-10 items-center justify-center rounded-xl bg-brand text-lg font-black text-on-brand">{store.name.charAt(0)}</span>
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

        <footer className="border-t border-border bg-surface">
          <div className="mx-auto grid max-w-5xl gap-4 px-4 py-6 text-sm text-text-muted sm:grid-cols-2">
            <div>
              <p className="font-semibold text-text">{store.name}</p>
              {branch?.address ? <p className="mt-1">{branch.city ? `${branch.city}، ` : ''}{branch.address}</p> : null}
              {branch && branch.opening_hours.length > 0 ? (
                <details className="mt-2">
                  <summary className="cursor-pointer text-text">ساعات کاری</summary>
                  <ul className="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                    {branch.opening_hours.map((h, i) => (
                      <li key={i} className="contents"><span>{h.weekday_label}</span><span className="tabular">{formatClock(h.opens_at)} تا {formatClock(h.closes_at)}</span></li>
                    ))}
                  </ul>
                </details>
              ) : null}
            </div>
            <div className="flex flex-col gap-2 sm:items-end">
              {store.contact.phone ? (
                <a href={`tel:${store.contact.phone}`} className="inline-flex items-center gap-2 hover:text-text"><Phone className="size-4" aria-hidden="true" /><Ltr>{store.contact.phone}</Ltr></a>
              ) : null}
              {store.contact.instagram ? (
                <a href={`https://instagram.com/${store.contact.instagram}`} rel="noopener noreferrer" target="_blank" className="inline-flex items-center gap-2 hover:text-text"><AtSign className="size-4" aria-hidden="true" /><Ltr>{store.contact.instagram}</Ltr></a>
              ) : null}
            </div>
          </div>
        </footer>
        <CartBar />
      </StoreProvider>
    </div>
  );
}
