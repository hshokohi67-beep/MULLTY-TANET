import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { CalendarClock, ReceiptText } from 'lucide-react';
import { Card, EmptyState, SelectField } from '@cafe/ui';
import { formatNumber } from '@cafe/locale';
import { LiveRefresh } from '@/components/LiveRefresh';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Branch, Order, TableRequestItem } from '@/lib/types';
import { OrderCard, TableRequestList } from './OrderBoard';
import { OrdersTabs } from './OrdersTabs';
import { ReadyNotifier } from './ReadyNotifier';

export const metadata: Metadata = { title: 'سفارش‌ها' };

const COLUMNS: { title: string; statuses: string[]; dot: string; empty: string }[] = [
  { title: 'جدید', statuses: ['placed'], dot: 'bg-info', empty: 'سفارش تازه‌ای نیست' },
  { title: 'در حال آماده‌سازی', statuses: ['accepted', 'preparing'], dot: 'bg-warning', empty: 'چیزی در دست آماده‌سازی نیست' },
  { title: 'آماده', statuses: ['ready'], dot: 'bg-success', empty: 'سفارش آماده‌ای منتظر نیست' },
  { title: 'در راه', statuses: ['out_for_delivery'], dot: 'bg-accent', empty: 'پیکی در راه نیست' },
];

export default async function OrdersPage({ searchParams }: PageProps<'/dashboard/orders'>) {
  const { can } = await requireMembership();

  if (!can('orders.view')) {
    redirect('/dashboard');
  }

  const params = await searchParams;
  const { data: branches } = await api<{ data: Branch[] }>('/branches');
  const branchId = typeof params.branch === 'string' ? params.branch : '';
  const query = new URLSearchParams({ status: 'open' });
  if (branchId) query.set('branch_id', branchId);

  // Orders the kitchen finished and auto-completed in the last few minutes never show as «آماده»;
  // they still deserve a "ready" notification for the waiter.
  const justCompleted = new URLSearchParams({ status: 'completed', completed_within: '3' });
  if (branchId) justCompleted.set('branch_id', branchId);

  const [{ data: orders }, { data: requests }, { data: finished }] = await Promise.all([
    api<{ data: Order[] }>(`/orders?${query}`),
    api<{ data: TableRequestItem[] }>('/table-requests'),
    api<{ data: Order[] }>(`/orders?${justCompleted}`),
  ]);

  const visibleRequests = branchId ? requests.filter((r) => r.table.branch_id === branchId) : requests;
  const canManage = can('orders.manage');
  // Pre-orders stay in their own lane until they are released to the kitchen.
  const now = new Date().toISOString();
  const upcoming = orders
    .filter((o) => o.kitchen_release_at && o.kitchen_release_at > now && ['placed', 'accepted'].includes(o.status))
    .sort((a, b) => (a.scheduled_for ?? '').localeCompare(b.scheduled_for ?? ''));
  const current = orders.filter((o) => !upcoming.includes(o));
  const columns = COLUMNS.filter((c) => c.statuses[0] !== 'out_for_delivery' || current.some((o) => o.status === 'out_for_delivery'));

  return (
    <div className="flex flex-col gap-5">
      <LiveRefresh endpoint="/dashboard/orders/live" seconds={5} />
      <PageHeader
        title="سفارش‌های باز"
        description={`${formatNumber(current.length)} سفارش در جریان${upcoming.length ? ` • ${formatNumber(upcoming.length)} پیش‌سفارش` : ''} • به‌روزرسانی خودکار با هر تغییر`}
        actions={<div className="flex flex-wrap items-end gap-3">
          <ReadyNotifier ready={[...orders.filter((o) => o.status === 'ready'), ...finished].map((o) => ({ id: o.id, number: o.daily_number, label: o.table?.label ?? o.contact_name ?? o.type_label }))} />
          <OrdersTabs current="open" />
          {branches.length > 1 ? (
          <form className="w-48">
            <SelectField label="شعبه" name="branch" defaultValue={branchId}>
              <option value="">همه‌ی شعبه‌ها</option>
              {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
            </SelectField>
            <button type="submit" className="sr-only">اعمال</button>
          </form>
          ) : null}
        </div>}
      />

      {visibleRequests.length > 0 ? <TableRequestList requests={visibleRequests} canManage={canManage} /> : null}

      {upcoming.length > 0 ? (
        <section aria-labelledby="col-preorders" className="rounded-2xl border border-info/20 bg-info-soft/50 p-3">
          <h2 id="col-preorders" className="mb-3 flex items-center gap-2 px-1 text-sm font-semibold">
            <CalendarClock className="size-4 text-info" aria-hidden="true" />
            پیش‌سفارش‌ها
            <span className="text-xs font-normal text-text-muted">به‌ترتیب زمان تحویل • سر وقت خودکار به آشپزخانه می‌روند</span>
            <span className="ms-auto rounded-full bg-surface px-2 py-0.5 text-xs font-medium text-text-muted">{formatNumber(upcoming.length)}</span>
          </h2>
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            {upcoming.map((order) => <OrderCard key={order.id} order={order} canManage={canManage} />)}
          </div>
        </section>
      ) : null}

      {orders.length === 0 && finished.length === 0 ? (
        <Card><EmptyState icon={<ReceiptText />} title="سفارش بازی ندارید" description="سفارش‌های جدید از منوی آنلاین، QR میزها و صندوق همین‌جا ظاهر می‌شوند و این صفحه خودکار به‌روز می‌شود." /></Card>
      ) : (
        <div className={`grid gap-4 md:grid-cols-2 ${columns.length > 3 ? 'xl:grid-cols-4' : 'xl:grid-cols-3'}`}>
          {columns.map((column) => {
            const list = current.filter((o) => column.statuses.includes(o.status)).sort((a, b) => (a.scheduled_for ?? a.placed_at).localeCompare(b.scheduled_for ?? b.placed_at));

            return (
              <section key={column.title} aria-labelledby={`col-${column.statuses[0]}`} className="flex flex-col gap-3 rounded-2xl bg-surface-muted/70 p-3">
                <h2 id={`col-${column.statuses[0]}`} className="flex items-center gap-2 px-1 text-sm font-semibold">
                  <span className={`size-2 rounded-full ${column.dot}`} aria-hidden="true" />
                  {column.title}
                  <span className="ms-auto rounded-full bg-surface px-2 py-0.5 text-xs font-medium text-text-muted">{formatNumber(list.length)}</span>
                </h2>
                {list.length === 0 ? <p className="rounded-xl border border-dashed border-border-strong px-3 py-6 text-center text-xs text-text-subtle">{column.empty}</p> : null}
                {list.map((order) => <OrderCard key={order.id} order={order} canManage={canManage} />)}
              </section>
            );
          })}
        </div>
      )}
    </div>
  );
}
