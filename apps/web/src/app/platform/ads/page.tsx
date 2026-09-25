import type { Metadata } from 'next';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import type { Placement, PlatformCampaign } from '@/lib/ads-types';
import { AdsReview } from './AdsReview';

export const metadata: Metadata = { title: 'تبلیغات' };

const FILTERS = ['pending', 'issues', 'paid', 'approved', 'rejected', 'suspended', 'cancelled'] as const;

/** Review queue for ads in «خوراک‌گردی», every campaign, and the placement prices. */
export default async function PlatformAdsPage({ searchParams }: PageProps<'/platform/ads'>) {
  const raw = (await searchParams).status;
  const status = FILTERS.find((f) => f === raw) ?? null;
  const { data } = await api<{ data: { campaigns: PlatformCampaign[]; counts: Record<string, number>; placements: Placement[] } }>(
    `/platform/ads${status ? `?status=${status}` : ''}`, { tenant: false },
  );

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="تبلیغات" description="کمپین‌های کافه‌ها پیش از نمایش اینجا بررسی می‌شوند. قیمت و ظرفیت جایگاه‌ها هم از همین‌جا تنظیم می‌شود." />
      <AdsReview campaigns={data.campaigns} counts={data.counts} placements={data.placements} status={status} />
    </div>
  );
}
