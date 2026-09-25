import type { Metadata } from 'next';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import type { FeatureDef, Plan } from '@/lib/billing-types';
import { PlanEditor } from '../PlatformClient';

export const metadata: Metadata = { title: 'پلن‌ها' };

/** Prices and what each plan includes. Changes apply to everyone on the plan immediately. */
export default async function PlatformPlansPage() {
  const { data } = await api<{ data: { plans: Plan[]; features: FeatureDef[] } }>('/platform/plans', { tenant: false });

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="پلن‌ها" description="تغییر امکانات و سقف‌ها فوراً برای همه‌ی مشترکان همان پلن اعمال می‌شود؛ قیمت جدید از صورت‌حساب بعدی." />
      {data.plans.map((p) => <PlanEditor key={p.id} plan={p} features={data.features} />)}
    </div>
  );
}
