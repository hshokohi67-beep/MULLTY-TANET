import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { Checkout } from '@/components/store/Checkout';
import { ApiError } from '@/lib/api';
import { getStorefront, readCookie, sf } from '@/lib/storefront';
import type { Club, CustomerAddress } from '@/lib/storefront-types';

export const metadata: Metadata = { title: 'سبد خرید', robots: { index: false, follow: false } };

/** Cart and checkout. Addresses and the wallet balance load server-side for signed-in customers. */
export default async function CartPage({ params }: PageProps<'/s/[tenant]/cart'>) {
  const { tenant } = await params;
  const store = await getStorefront(tenant);
  if (!store) notFound();

  const signedIn = Boolean(await readCookie(tenant, 'customer'));
  const [addresses, club] = signedIn
    ? await Promise.all([
      sf<{ data: CustomerAddress[] }>(tenant, '/customer/addresses').then((r) => r.data).catch(() => [] as CustomerAddress[]),
      sf<{ data: Club }>(tenant, '/customer/club').then((r) => r.data).catch((e: unknown) => {
        if (e instanceof ApiError) return null;
        throw e;
      }),
    ])
    : [[] as CustomerAddress[], null];

  return <Checkout addresses={addresses} walletBalance={club?.program.wallet_payments ? club.wallet_balance : 0} />;
}
