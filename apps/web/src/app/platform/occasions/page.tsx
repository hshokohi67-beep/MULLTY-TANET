import type { Metadata } from 'next';
import { api } from '@/lib/api';
import { OccasionAdmin, type AdminOccasion } from './OccasionAdmin';

export const metadata: Metadata = { title: 'مناسبت‌ها' };

/**
 * The platform's occasions calendar: every café hears about these. Hijri dates are computed and
 * stay «تقریبی» until confirmed here; a one-off occasion (e.g. a national match day) can be added.
 */
export default async function PlatformOccasionsPage() {
  const { data } = await api<{ data: AdminOccasion[] }>('/platform/occasions', { tenant: false });

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="text-2xl font-black">تقویم مناسبت‌ها</h1>
        <p className="mt-1 text-sm text-text-muted">
          کافه‌ها پیش از هر مناسبت در داشبوردشان خبردار می‌شوند. تاریخ‌های قمری محاسبه‌ی تقریبی‌اند؛ پس از اعلام رسمی (رؤیت هلال) تأیید یا یک روز جابه‌جایشان کنید.
        </p>
      </div>
      <OccasionAdmin occasions={data} />
    </div>
  );
}
