import type { Metadata } from 'next';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { ModerationTable, type ListingRow } from './ModerationTable';

export const metadata: Metadata = { title: 'بازارگاه' };

/** Every café that set up a marketplace entry: live or not, hide with a reason, feature until a date. */
export default async function PlatformMarketplacePage() {
  const { data } = await api<{ data: ListingRow[] }>('/platform/marketplace', { tenant: false });

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="بازارگاه" description="نظارت بر کافه‌های معرفی‌شده در کافه‌گردی: توقف نمایش با ذکر دلیل، و نمایش ویژه تا یک تاریخ." />
      <ModerationTable rows={data} />
    </div>
  );
}
