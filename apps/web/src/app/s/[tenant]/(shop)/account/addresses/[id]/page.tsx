import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { ChevronRight } from 'lucide-react';
import { AddressForm } from '@/components/store/AddressForm';
import { readCookie, sf } from '@/lib/storefront';
import type { CustomerAddress } from '@/lib/storefront-types';

export const metadata: Metadata = { title: 'آدرس', robots: { index: false, follow: false } };

/** New (`/new`) or existing address. After saving, back to the cart or the account. */
export default async function AddressPage({ params, searchParams }: PageProps<'/s/[tenant]/account/addresses/[id]'>) {
  const { tenant, id } = await params;
  const back = (await searchParams).next === 'cart' ? `/s/${tenant}/cart` : `/s/${tenant}/account`;
  if (!(await readCookie(tenant, 'customer'))) redirect(`/s/${tenant}/login?next=account`);

  let address: CustomerAddress | null = null;
  if (id !== 'new') {
    const all = await sf<{ data: CustomerAddress[] }>(tenant, '/customer/addresses').then((r) => r.data).catch(() => []);
    address = all.find((a) => a.id === id) ?? null;
    if (!address) notFound();
  }

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-4 pt-5">
      <Link href={back} className="inline-flex items-center gap-1 text-sm text-text-muted hover:text-text"><ChevronRight className="size-4" aria-hidden="true" />بازگشت</Link>
      <h1 className="text-2xl font-bold">{address ? 'ویرایش آدرس' : 'آدرس جدید'}</h1>
      <div className="rounded-2xl border border-border bg-surface p-5"><AddressForm address={address} next={back} /></div>
    </div>
  );
}
