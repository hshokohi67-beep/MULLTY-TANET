import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { Landing } from '@/components/store/landing/Landing';
import { MenuHome } from '@/components/store/MenuHome';
import { ShopChrome } from '@/components/store/ShopChrome';
import { getLanding, getMenu, getStorefront, storeUrl } from '@/lib/storefront';

export async function generateMetadata({ params }: PageProps<'/s/[tenant]'>): Promise<Metadata> {
  const { tenant } = await params;
  const [store, landing] = await Promise.all([getStorefront(tenant), getLanding(tenant)]);
  if (!store) return {};

  const title = store.branding?.seo_title ?? (landing ? store.name : `منو و سفارش آنلاین ${store.name}`);
  const description = landing?.content.hero.subtitle ?? store.branding?.seo_description ?? undefined;
  const image = landing?.media.hero_photo?.url ?? store.branding?.cover_url ?? store.branding?.logo_url;

  return {
    title: { absolute: title },
    description,
    alternates: { canonical: storeUrl(tenant) },
    openGraph: { title, description, ...(image ? { images: [{ url: image }] } : {}) },
  };
}

/**
 * The café's home: its landing page when one is published (the «منو و سفارش» button opens /menu),
 * otherwise the menu itself, as before.
 */
export default async function StoreHome({ params, searchParams }: PageProps<'/s/[tenant]'>) {
  const { tenant } = await params;
  const [store, landing] = await Promise.all([getStorefront(tenant), getLanding(tenant)]);
  if (!store) notFound();

  if (!landing) {
    return <ShopChrome tenant={tenant} store={store}><MenuHome tenant={tenant} store={store} query={await searchParams} /></ShopChrome>;
  }

  const menu = store.branches[0] ? await getMenu(tenant, store.branches[0].slug) : null;
  const branch = store.branches[0];
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'CafeOrCoffeeShop',
    name: store.name,
    url: storeUrl(tenant),
    hasMenu: storeUrl(tenant, '/menu'),
    ...(landing.content.hero.subtitle ? { description: landing.content.hero.subtitle } : {}),
    ...(store.branding?.logo_url ? { logo: store.branding.logo_url } : {}),
    ...(landing.media.hero_photo ? { image: landing.media.hero_photo.url } : {}),
    ...(store.contact.phone ? { telephone: store.contact.phone } : {}),
    ...(branch?.address ? { address: { '@type': 'PostalAddress', streetAddress: branch.address, addressLocality: branch.city ?? undefined, addressCountry: 'IR' } } : {}),
    ...(branch?.latitude != null ? { geo: { '@type': 'GeoCoordinates', latitude: branch.latitude, longitude: branch.longitude } } : {}),
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, '\\u003c') }} />
      <Landing tenant={tenant} store={store} landing={landing} menu={menu} />
    </>
  );
}
