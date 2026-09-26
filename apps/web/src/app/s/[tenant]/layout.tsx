import type { Metadata, Viewport } from 'next';
import { notFound } from 'next/navigation';
import { brandCss, brandTokens } from '@cafe/ui';
import { CartBar } from '@/components/store/CartBar';
import { StoreProvider } from '@/components/store/StoreProvider';
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
 * The shell shared by every storefront page of one café: its brand colour (made readable in both
 * themes), the visitor state provider and the cart bar. The shop pages add their header and footer
 * in (shop)/layout; the landing page (the home page, when published) draws its own frame.
 */
export default async function StoreLayout({ children, params }: LayoutProps<'/s/[tenant]'>) {
  const { tenant } = await params;
  const store = await getStorefront(tenant);
  if (!store) notFound();

  const css = brandCss(store.branding?.primary_color, '.store');

  return (
    <div className="store flex min-h-dvh flex-col">
      {css ? <style dangerouslySetInnerHTML={{ __html: css }} /> : null}
      <StoreProvider tenant={tenant} store={store}>
        {children}
        <CartBar />
      </StoreProvider>
    </div>
  );
}
