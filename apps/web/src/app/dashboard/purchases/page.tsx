import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { Plus, ShoppingCart } from 'lucide-react';
import { Badge, Card, CardHeader, cx, EmptyState } from '@cafe/ui';
import { formatJalaliDate, formatMoney, formatNumber } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { PurchaseOrder, Supplier } from '@/lib/inventory-types';
import { STATUS_TONE } from './status';
import { SupplierList } from './SupplierList';

export const metadata: Metadata = { title: 'خرید' };

const TABS = [
  { key: 'open', label: 'در جریان' },
  { key: 'unpaid', label: 'تسویه‌نشده' },
  { key: 'received', label: 'تحویل‌شده' },
  { key: '', label: 'همه' },
] as const;

/** Purchase orders by state, and suppliers with what we still owe them. */
export default async function PurchasesPage({ searchParams }: PageProps<'/dashboard/purchases'>) {
  const { can } = await requireMembership();
  if (!can('purchasing.manage')) redirect('/dashboard');
  const status = (await searchParams).status;
  const tab = typeof status === 'string' && TABS.some((t) => t.key === status) ? status : 'open';

  const [{ data: orders }, { data: suppliers }] = await Promise.all([
    api<{ data: PurchaseOrder[] }>(`/inventory/purchases${tab ? `?status=${tab}` : ''}`),
    api<{ data: Supplier[] }>('/inventory/suppliers'),
  ]);
  const owed = suppliers.reduce((s, x) => s + x.owed, 0);

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="خرید" description={owed ? `مانده‌ی بدهی به تأمین‌کننده‌ها: ${formatMoney(owed)}` : 'سفارش خرید مواد اولیه، تحویل و پرداخت به تأمین‌کننده'}
        actions={<Link href="/dashboard/purchases/new" className="inline-flex h-10 items-center gap-2 rounded-lg bg-brand px-3.5 text-sm font-semibold text-on-brand hover:bg-brand-strong"><Plus className="size-4" aria-hidden="true" />سفارش خرید</Link>} />

      <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div className="flex flex-col gap-3">
          <nav aria-label="وضعیت" className="flex gap-1 self-start rounded-full bg-surface-muted p-1">
            {TABS.map((t) => (
              <Link key={t.key} href={t.key ? `/dashboard/purchases?status=${t.key}` : '/dashboard/purchases?status='} aria-current={tab === t.key ? 'page' : undefined}
                className={cx('rounded-full px-3.5 py-1.5 text-sm', tab === t.key ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text')}>{t.label}</Link>
            ))}
          </nav>
          <Card>
            {orders.length === 0 ? <EmptyState icon={<ShoppingCart />} title="سفارشی در این بخش نیست" description="با «سفارش خرید» مواد را از تأمین‌کننده سفارش دهید؛ با ثبت تحویل، موجودی و قیمت میانگین خودکار به‌روز می‌شود." /> : (
              <ul className="divide-y divide-border">
                {orders.map((o) => (
                  <li key={o.id}>
                    <Link href={`/dashboard/purchases/${o.id}`} className="flex flex-wrap items-center gap-3 px-4 py-3.5 hover:bg-surface-muted/60">
                      <span className="tabular w-12 font-bold">#{formatNumber(o.number)}</span>
                      <span className="min-w-0 flex-1">
                        <span className="block font-medium">{o.supplier?.name}</span>
                        <span className="block text-xs text-text-muted">{formatJalaliDate(o.created_at)}{o.branch ? ` • ${o.branch.name}` : ''}</span>
                      </span>
                      <Badge tone={STATUS_TONE[o.status]} dot>{o.status_label}</Badge>
                      <span className="tabular w-32 text-end">
                        <span className="block text-sm font-semibold">{formatMoney(o.total)}</span>
                        {o.status === 'cancelled' || !o.has_receipts ? null : o.balance_due > 0 ? <span className="block text-xs text-warning">مانده {formatMoney(o.balance_due)}</span> : <span className="block text-xs text-success">تسویه</span>}
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>

        <Card>
          <CardHeader title="تأمین‌کننده‌ها" />
          <SupplierList suppliers={suppliers} />
        </Card>
      </div>
    </div>
  );
}
