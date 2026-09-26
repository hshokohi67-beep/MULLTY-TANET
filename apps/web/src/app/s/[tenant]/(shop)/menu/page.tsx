import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { MenuHome, pickBranch } from '@/components/store/MenuHome';
import { getLanding, getStorefront, storeUrl } from '@/lib/storefront';

export async function generateMetadata({ params, searchParams }: PageProps<'/s/[tenant]/menu'>): Promise<Metadata> {
  const { tenant } = await params;
  const store = await getStorefront(tenant);
  if (!store) return {};
  const branch = pickBranch(store.branches, (await searchParams).branch);
  const title = store.branding?.seo_title ?? `منو و سفارش آنلاین ${store.name}`;
  // Without a landing page the root is the menu, so that stays the canonical address.
  const home = (await getLanding(tenant)) ? storeUrl(tenant, '/menu') : storeUrl(tenant);

  return {
    title: { absolute: title },
    alternates: { canonical: branch && branch !== store.branches[0] ? storeUrl(tenant, `/menu?branch=${encodeURIComponent(branch.slug)}`) : home },
    openGraph: { title, description: store.branding?.seo_description ?? undefined },
  };
}

/** The full menu (the landing page's «منو و سفارش» button, table QR codes and every "back to menu" link land here). */
export default async function MenuPage({ params, searchParams }: PageProps<'/s/[tenant]/menu'>) {
  const { tenant } = await params;
  const store = await getStorefront(tenant);
  if (!store) notFound();

  return <MenuHome tenant={tenant} store={store} query={await searchParams} />;
}
