import { notFound } from 'next/navigation';
import { ShopChrome } from '@/components/store/ShopChrome';
import { getStorefront } from '@/lib/storefront';

/** Menu, product, cart, checkout and account pages: the shop header and footer around them. */
export default async function ShopLayout({ children, params }: LayoutProps<'/s/[tenant]'>) {
  const { tenant } = await params;
  const store = await getStorefront(tenant);
  if (!store) notFound();

  return <ShopChrome tenant={tenant} store={store}>{children}</ShopChrome>;
}
