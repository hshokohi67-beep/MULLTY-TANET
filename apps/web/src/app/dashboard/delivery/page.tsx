import Link from 'next/link';
import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { Alert, Card, CardHeader, EmptyState } from '@cafe/ui';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Branch, DeliveryZone } from '@/lib/types';
import { ZonesMap } from './ZonesMap';
import { ZoneForm } from './ZoneForm';

export const metadata: Metadata = { title: 'محدوده‌های ارسال' };

export default async function DeliveryPage() {
  const { can } = await requireMembership();

  if (!can('delivery.manage')) {
    redirect('/dashboard');
  }

  const [{ data: zones }, { data: branches }] = await Promise.all([
    api<{ data: DeliveryZone[] }>('/delivery-zones'),
    api<{ data: Branch[] }>('/branches'),
  ]);

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="محدوده‌های ارسال"
        description="محدوده‌ها دایره‌ای‌اند و از موقعیت شعبه حساب می‌شوند. برای هزینه‌ی پله‌ای، چند محدوده بسازید (مثلاً تا ۲ کیلومتر رایگان، تا ۵ کیلومتر با هزینه)."
      />

      {branches.map((branch) => {
        const list = zones.filter((z) => z.branch_id === branch.id);
        const located = branch.latitude != null && branch.longitude != null;

        return (
          <Card key={branch.id}>
            <CardHeader title={branch.name} />
            <div className="flex flex-col gap-4 p-5">
              {!located ? (
                <Alert tone="warning">
                  محل این شعبه روی نقشه مشخص نشده است؛ تا آن را در <Link href={`/dashboard/branches/${branch.id}`} className="font-semibold underline">صفحه‌ی شعبه</Link> روی نقشه نزنید، ارسال با پیک فعال نمی‌شود.
                </Alert>
              ) : (
                <ZonesMap center={{ lat: Number(branch.latitude), lng: Number(branch.longitude) }} zones={list.map((z) => ({ id: z.id, name: z.name, radius_m: z.radius_m, is_active: z.is_active, delivery_fee: z.delivery_fee }))} />
              )}
              {list.length === 0 ? (
                <EmptyState title="این شعبه ارسال با پیک ندارد" description="با فرم زیر اولین محدوده را بسازید." />
              ) : (
                list.map((zone) => <ZoneForm key={zone.id} branchId={branch.id} zone={zone} />)
              )}
              <ZoneForm branchId={branch.id} />
            </div>
          </Card>
        );
      })}
    </div>
  );
}
