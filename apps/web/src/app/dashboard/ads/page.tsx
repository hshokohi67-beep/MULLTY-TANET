import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import type { AdsOverview } from '@/lib/ads-types';
import { requireMembership } from '@/lib/auth';
import type { StoreCard } from '@/lib/marketplace-types';
import type { Branding, Tenant } from '@/lib/types';
import { AdsManager } from './AdsManager';

export const metadata: Metadata = { title: 'تبلیغات' };

/** Paid placements in «کافه‌گردی»: results, campaigns, and a builder with a live preview. */
export default async function AdsPage({ searchParams }: PageProps<'/dashboard/ads'>) {
  const { can } = await requireMembership();
  if (!can('ads.manage')) redirect('/dashboard');

  const [{ data }, { data: tenant }, branding, listing] = await Promise.all([
    api<{ data: AdsOverview }>('/ads'),
    api<{ data: Tenant }>('/tenant'),
    api<{ data: Branding }>('/tenant/branding').then((r) => r.data).catch(() => null),
    can('marketplace.manage')
      ? api<{ data: { eligible: boolean; preview: StoreCard | null } }>('/marketplace/listing').then((r) => r.data).catch(() => null)
      : Promise.resolve(null),
  ]);
  const payment = (await searchParams).payment;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="تبلیغات در کافه‌گردی" description="بنر صفحه‌ی اول یا جایگاه بالای نتایج؛ هر کمپین پیش از نمایش بررسی می‌شود و فقط برای روزهای انتخابی هزینه دارد." />
      <AdsManager
        data={data}
        store={{ slug: tenant.slug, name: tenant.name, logo_url: branding?.logo_url ?? null, primary_color: branding?.primary_color ?? null }}
        listed={listing?.eligible ?? true}
        preview={listing?.preview ?? null}
        payment={typeof payment === 'string' ? payment : null}
      />
    </div>
  );
}
