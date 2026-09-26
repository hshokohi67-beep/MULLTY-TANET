import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { storeLink } from '@/lib/store-links';
import { requireMembership } from '@/lib/auth';
import type { Branch, RestaurantTable } from '@/lib/types';
import { AddTableForm, TableRow } from './TablesManager';
import { Card, CardHeader, EmptyState } from '@cafe/ui';

export const metadata: Metadata = { title: 'میزها و کدهای QR' };

export default async function TablesPage() {
  const { can, membership } = await requireMembership();

  if (!can('orders.view')) {
    redirect('/dashboard');
  }

  const [{ data: tables }, { data: branches }] = await Promise.all([
    api<{ data: RestaurantTable[] }>('/tables'),
    api<{ data: Branch[] }>('/branches'),
  ]);

  // The printed QR opens this tenant's storefront table page.
  const storefrontBase = storeLink(membership.tenant.slug, '/t');
  const canManage = can('tables.manage');

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="میزها و کدهای QR"
        description="مشتری با اسکن QR روی میز، منوی همان میز را می‌بیند و سفارش می‌دهد. هر کد غیرقابل‌حدس است و با ساخت کد جدید، کد قبلی باطل می‌شود."
      />
      {canManage ? <AddTableForm branches={branches} /> : null}

      {branches.map((branch) => {
        const list = tables.filter((t) => t.branch_id === branch.id);

        return (
          <Card key={branch.id}>
            <CardHeader title={branch.name} />
            {list.length === 0 ? (
              <EmptyState title="میزی تعریف نشده" description="میزها را اضافه کنید تا برای هرکدام QR بسازید." />
            ) : (
              <ul className="divide-y divide-border">
                {list.map((table) => (
                  <TableRow key={table.id} table={table} storefrontBase={storefrontBase} canManage={canManage} canCloseSession={can('orders.manage')} />
                ))}
              </ul>
            )}
          </Card>
        );
      })}
    </div>
  );
}
