import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { CalendarHeart } from 'lucide-react';
import { Card, EmptyState } from '@cafe/ui';
import { OccasionCard } from '@/components/occasions/OccasionCard';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { OccasionKind, UpcomingOccasion } from '@/lib/occasion-types';
import { OccasionPreferences } from './OccasionPreferences';

export const metadata: Metadata = { title: 'تقویم مناسبت‌ها' };

interface SettingItem { key: string; value: unknown }

/**
 * The café's occasions calendar: Iranian and global occasions of the next four months, each with
 * ideas and one-click actions. The platform keeps the calendar; the café only answers it.
 */
export default async function OccasionsPage() {
  const { can } = await requireMembership();
  if (!can('occasions.view')) redirect('/dashboard');

  const [{ data: items }, settings] = await Promise.all([
    api<{ data: UpcomingOccasion[] }>('/occasions/upcoming?days=120'),
    can('settings.update') ? api<{ data: SettingItem[] }>('/tenant/settings').then((r) => r.data) : Promise.resolve(null),
  ]);
  const kinds = String(settings?.find((s) => s.key === 'occasions.kinds')?.value ?? '').split(',').filter(Boolean) as OccasionKind[];
  const notify = Boolean(settings?.find((s) => s.key === 'occasions.notify')?.value ?? true);

  const soon = items.filter((o) => o.in_lead || o.is_ongoing);
  const later = items.filter((o) => !o.in_lead && !o.is_ongoing);
  const card = (o: UpcomingOccasion) => <OccasionCard key={o.id} occasion={o} canDiscounts={can('discounts.manage')} canCatalog={can('catalog.manage')} />;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="تقویم مناسبت‌ها"
        description="مناسبت‌های ایرانی، مذهبی و جهانی چهار ماه آینده، با ایده برای منو و تخفیف. زمان هر مناسبت که نزدیک شود، در زنگوله و با اعلان مرورگر خبرتان می‌کنیم."
      />

      {items.length === 0 ? (
        <Card><EmptyState icon={<CalendarHeart />} title="فعلاً مناسبتی در پیش نیست" description="مناسبت‌هایی که دنبال می‌کنید را پایین همین صفحه انتخاب کنید." /></Card>
      ) : (
        <>
          {soon.length ? (
            <section aria-labelledby="soon" className="flex flex-col gap-3">
              <h2 id="soon" className="text-lg font-bold">نزدیک است</h2>
              {soon.map(card)}
            </section>
          ) : null}
          {later.length ? (
            <section aria-labelledby="later" className="flex flex-col gap-3">
              <h2 id="later" className="text-lg font-bold">بعدتر</h2>
              <div className="grid gap-3 lg:grid-cols-2">{later.map((o) => <OccasionCard key={o.id} occasion={o} canDiscounts={can('discounts.manage')} canCatalog={can('catalog.manage')} compact />)}</div>
            </section>
          ) : null}
        </>
      )}

      {settings ? <OccasionPreferences kinds={kinds} notify={notify} /> : null}
    </div>
  );
}
