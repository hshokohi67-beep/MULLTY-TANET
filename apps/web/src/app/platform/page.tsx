import type { Metadata } from 'next';
import { StatTile } from '@cafe/ui';
import { formatNumber } from '@cafe/locale';
import { AlertTriangle, Building2, CheckCircle2, Hourglass } from 'lucide-react';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import type { FeatureDef, Plan } from '@/lib/billing-types';
import { TenantTable, type PlatformRow } from './PlatformClient';

export const metadata: Metadata = { title: 'مشترکان' };

/** Every café's subscription at a glance, with the support tools (extend, grant, confirm transfer). */
export default async function PlatformPage() {
  const [{ data: rows }, { data: catalog }] = await Promise.all([
    api<{ data: PlatformRow[] }>('/platform/subscriptions', { tenant: false }),
    api<{ data: { plans: Plan[]; features: FeatureDef[] } }>('/platform/plans', { tenant: false }),
  ]);
  const count = (state: string) => rows.filter((r) => r.subscription?.state === state).length;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="مشترکان" description="وضعیت اشتراک همه‌ی کافه‌ها؛ تمدید دستی، امکان ویژه و تأیید حواله‌ی بانکی." />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile label="کافه‌ها" value={formatNumber(rows.length)} icon={<Building2 />} />
        <StatTile label="فعال" value={formatNumber(count('active'))} icon={<CheckCircle2 />} />
        <StatTile label="آزمایشی" value={formatNumber(count('trial'))} icon={<Hourglass />} />
        <StatTile label="مهلت یا فقط‌خواندنی" value={formatNumber(count('grace') + count('read_only'))} icon={<AlertTriangle />} />
      </div>
      <TenantTable rows={rows} features={catalog.features} />
    </div>
  );
}
