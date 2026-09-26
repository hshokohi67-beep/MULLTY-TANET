import type { Metadata } from 'next';
import { CustomerLogin } from '@/components/store/CustomerLogin';

export const metadata: Metadata = { title: 'ورود', robots: { index: false, follow: false } };

const NEXT = { cart: '/cart', account: '/account' } as const;

export default async function LoginPage({ params, searchParams }: PageProps<'/s/[tenant]/login'>) {
  const { tenant } = await params;
  const next = (await searchParams).next;
  // Only known destinations: never an open redirect.
  const target = `/s/${tenant}${typeof next === 'string' && next in NEXT ? NEXT[next as keyof typeof NEXT] : '/account'}`;

  return <CustomerLogin next={target} />;
}
