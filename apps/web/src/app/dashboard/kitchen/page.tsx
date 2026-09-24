import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { Card, CardHeader, EmptyState } from '@cafe/ui';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { getTenantSlug } from '@/lib/session';
import type { Branch, KitchenSetup, Product, SettingItem } from '@/lib/types';
import { DevicesPanel, KitchenSettingsForm, RoutingEditor, StationEditor } from './KitchenForms';

export const metadata: Metadata = { title: 'آشپزخانه' };

export default async function KitchenPage() {
  const { can } = await requireMembership();

  if (!can('kds.manage')) {
    redirect('/dashboard');
  }

  const [{ data: setup }, { data: branches }, { data: products }, settings, tenant] = await Promise.all([
    api<{ data: KitchenSetup }>('/kitchen/setup'),
    api<{ data: Branch[] }>('/branches'),
    api<{ data: Product[] }>('/catalog/products?per_page=200&status=active'),
    can('settings.view') ? api<{ data: SettingItem[] }>('/tenant/settings').then((r) => r.data) : Promise.resolve([] as SettingItem[]),
    getTenantSlug(),
  ]);

  const setting = (key: string, fallback: boolean) => {
    const item = settings.find((s) => s.key === key);
    return item ? Boolean(item.value) : fallback;
  };

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="آشپزخانه"
        description="ایستگاه‌ها، مسیر آیتم‌ها و دستگاه‌های نمایشگر آشپزخانه"
        actions={
          <Link href="/kds" target="_blank" className="inline-flex h-10 items-center rounded-md bg-brand px-4 text-sm font-medium text-on-brand hover:bg-brand-strong">
            باز کردن نمایشگر
          </Link>
        }
      />

      {branches.map((branch) => {
        const stations = setup.stations.filter((s) => s.branch_id === branch.id);
        const takenBy = Object.fromEntries(stations.flatMap((s) => s.product_ids.map((id) => [id, s.name])));

        return (
          <Card key={branch.id}>
            <CardHeader title={`ایستگاه‌های ${branch.name}`} description="هر سفارش بین ایستگاه‌ها تقسیم می‌شود؛ مثلاً نوشیدنی‌ها به بار و غذا به آشپزخانه." />
            <div className="flex flex-col gap-4 p-5">
              {stations.length === 0 ? (
                <EmptyState title="هنوز ایستگاهی ندارید" description="با اولین ایستگاه (مثلاً «بار قهوه»)، سفارش‌های این شعبه روی نمایشگر آشپزخانه می‌آیند." />
              ) : (
                stations.map((s) => (
                  <div key={s.id} className="rounded-md border border-border p-4">
                    <StationEditor station={s} branchId={branch.id} />
                    {stations.length > 1 ? <RoutingEditor station={s} products={products} takenBy={takenBy} /> : null}
                  </div>
                ))
              )}
              <StationEditor branchId={branch.id} />
            </div>
          </Card>
        );
      })}

      <DevicesPanel devices={setup.devices} branches={branches} stations={setup.stations} tenant={tenant ?? ''} />

      {can('settings.update') ? (
        <KitchenSettingsForm dineIn={setting('kds.auto_complete_dine_in', true)} takeaway={setting('kds.auto_complete_takeaway', false)} />
      ) : null}
    </div>
  );
}
