import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { Badge, Card, EmptyState, SelectField } from '@cafe/ui';
import { addDays, formatJalaliDate, formatMoney, formatNumber, formatPhone, formatTime, gregorianToJalali, todayIn } from '@cafe/locale';
import { JalaliDateField } from '@/components/JalaliDateField';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Branch, Order } from '@/lib/types';
import { OrdersTabs } from '../OrdersTabs';

export const metadata: Metadata = { title: 'تاریخچه‌ی سفارش‌ها' };

const STATUS_TONE: Record<string, 'success' | 'danger' | 'warning' | 'info' | 'neutral'> = {
  completed: 'success', cancelled: 'danger', rejected: 'danger', pending_payment: 'warning', ready: 'info',
};

function presets(today: string): { key: string; label: string; from: string; to: string }[] {
  const j = gregorianToJalali(today);
  const monthStart = addDays(today, -(j.day - 1));

  return [
    { key: 'today', label: 'امروز', from: today, to: today },
    { key: 'yesterday', label: 'دیروز', from: addDays(today, -1), to: addDays(today, -1) },
    { key: '7d', label: '۷ روز اخیر', from: addDays(today, -6), to: today },
    { key: 'month', label: 'این ماه', from: monthStart, to: today },
    { key: '30d', label: '۳۰ روز اخیر', from: addDays(today, -29), to: today },
  ];
}

interface Summary { orders: number; completed: number; cancelled: number; revenue: number; average: number; discounts: number }

export default async function OrderHistoryPage({ searchParams }: PageProps<'/dashboard/orders/history'>) {
  const { can } = await requireMembership();

  if (!can('orders.view')) {
    redirect('/dashboard');
  }

  const params = await searchParams;
  const str = (key: string) => (typeof params[key] === 'string' ? (params[key] as string) : '');
  const today = todayIn();
  const ranges = presets(today);
  const range = ranges.find((r) => r.key === str('range'));
  const from = range?.from ?? (str('from') || today);
  const to = range?.to ?? (str('to') || today);
  const filters = { from, to, status: str('status'), type: str('type'), branch_id: str('branch'), q: str('q') };
  const query = new URLSearchParams(Object.entries(filters).filter(([, v]) => v !== ''));
  const cursor = str('cursor');

  const [{ data: orders, meta }, { data: summary }, { data: branches }] = await Promise.all([
    api<{ data: Order[]; meta: { next_cursor: string | null } }>(`/orders?${query}${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`),
    api<{ data: Summary }>(`/orders/summary?${query}`),
    api<{ data: Branch[] }>('/branches'),
  ]);

  const pageParams = { from, to, status: filters.status, type: filters.type, branch: filters.branch_id, q: filters.q };
  const keep = (extra: Record<string, string>) => new URLSearchParams(Object.entries({ ...pageParams, ...extra }).filter(([, v]) => v !== '')).toString();
  const sameDay = from === to;

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="تاریخچه‌ی سفارش‌ها"
        description={sameDay ? formatJalaliDate(`${from}T12:00:00Z`) : `${formatJalaliDate(`${from}T12:00:00Z`)} تا ${formatJalaliDate(`${to}T12:00:00Z`)}`}
        actions={<OrdersTabs current="history" />}
      />

      <div className="flex flex-wrap gap-2">
        {ranges.map((r) => (
          <Link
            key={r.key}
            href={`/dashboard/orders/history?${new URLSearchParams(Object.entries({ range: r.key, status: filters.status, type: filters.type, branch: filters.branch_id, q: filters.q }).filter(([, v]) => v !== ''))}`}
            className={`rounded-full border px-3 py-1 text-sm ${from === r.from && to === r.to ? 'border-brand bg-brand-soft text-brand-strong' : 'border-border hover:bg-surface-muted'}`}
          >
            {r.label}
          </Link>
        ))}
      </div>

      <form className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4" role="search" aria-label="فیلتر سفارش‌ها">
        <JalaliDateField label="از" name="from" defaultValue={from} />
        <JalaliDateField label="تا" name="to" defaultValue={to} />
        <div className="w-36">
          <SelectField label="وضعیت" name="status" defaultValue={filters.status}>
            <option value="">همه</option>
            <option value="completed">تحویل شد</option>
            <option value="cancelled,rejected">لغو / رد شده</option>
            <option value="open">در جریان</option>
            <option value="pending_payment">در انتظار پرداخت</option>
          </SelectField>
        </div>
        <div className="w-36">
          <SelectField label="نوع" name="type" defaultValue={filters.type}>
            <option value="">همه</option>
            <option value="qr_table">میز (QR)</option>
            <option value="dine_in">سالن</option>
            <option value="takeaway">بیرون‌بر</option>
            <option value="delivery">ارسال</option>
            <option value="counter">پیشخوان</option>
            <option value="phone">تلفنی</option>
          </SelectField>
        </div>
        {branches.length > 1 ? (
          <div className="w-40">
            <SelectField label="شعبه" name="branch" defaultValue={filters.branch_id}>
              <option value="">همه</option>
              {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
            </SelectField>
          </div>
        ) : null}
        <div className="min-w-44 flex-1">
          <label htmlFor="order-q" className="mb-1.5 block text-sm font-medium">جستجو</label>
          <input id="order-q" name="q" defaultValue={filters.q} placeholder="شماره سفارش، موبایل یا نام…" className="h-10 w-full rounded-md border border-border-strong bg-surface px-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
        </div>
        <button type="submit" className="h-10 rounded-md bg-brand px-5 text-sm font-medium text-on-brand hover:bg-brand-strong">نمایش</button>
      </form>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {[
          { label: 'فروش', value: formatMoney(summary.revenue), hint: summary.discounts > 0 ? `پس از ${formatMoney(summary.discounts)} تخفیف` : 'بدون سفارش‌های لغوشده' },
          { label: 'تعداد سفارش', value: formatNumber(summary.orders), hint: `${formatNumber(summary.completed)} تحویل‌شده` },
          { label: 'میانگین هر سفارش', value: formatMoney(summary.average), hint: 'بدون لغوشده‌ها' },
          { label: 'لغو یا رد شده', value: formatNumber(summary.cancelled), hint: summary.orders > 0 ? `${formatNumber(Math.round((summary.cancelled / summary.orders) * 100))}٪ از سفارش‌ها` : '—' },
        ].map((tile) => (
          <Card key={tile.label} className="p-4">
            <p className="text-xs text-text-muted">{tile.label}</p>
            <p className="mt-1 text-xl font-bold">{tile.value}</p>
            <p className="mt-0.5 text-xs text-text-subtle">{tile.hint}</p>
          </Card>
        ))}
      </div>

      <Card>
        {orders.length === 0 ? (
          <EmptyState title="سفارشی پیدا نشد" description="بازه‌ی تاریخ یا فیلترها را تغییر دهید." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="border-b border-border text-xs text-text-muted">
                <tr>
                  <th scope="col" className="px-4 py-3 text-start font-medium">سفارش</th>
                  <th scope="col" className="px-4 py-3 text-start font-medium">زمان</th>
                  <th scope="col" className="px-4 py-3 text-start font-medium">نوع</th>
                  <th scope="col" className="px-4 py-3 text-start font-medium">مشتری</th>
                  <th scope="col" className="px-4 py-3 text-start font-medium">مبلغ</th>
                  <th scope="col" className="px-4 py-3 text-start font-medium">پرداخت</th>
                  <th scope="col" className="px-4 py-3 text-start font-medium">وضعیت</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {orders.map((o) => (
                  <tr key={o.id} className="hover:bg-surface-muted">
                    <td className="px-4 py-3 font-semibold">
                      <Link href={`/dashboard/orders/${o.id}`} className="text-brand hover:underline">#{formatNumber(o.daily_number)}</Link>
                    </td>
                    <td className="px-4 py-3 text-text-muted">{sameDay ? formatTime(o.placed_at) : `${formatJalaliDate(o.placed_at)} ${formatTime(o.placed_at)}`}</td>
                    <td className="px-4 py-3">{o.table ? o.table.label : o.type_label}</td>
                    <td className="px-4 py-3">
                      {o.contact_name ?? '—'}
                      {o.contact_phone ? <span className="block text-xs text-text-muted" dir="ltr">{formatPhone(o.contact_phone)}</span> : null}
                    </td>
                    <td className="px-4 py-3 font-medium">{formatMoney(o.total)}</td>
                    <td className="px-4 py-3">
                      <Badge tone={o.payment_status === 'paid' ? 'success' : o.needs_refund ? 'danger' : 'neutral'}>{o.needs_refund ? 'نیاز به بازگشت وجه' : o.payment_status_label}</Badge>
                    </td>
                    <td className="px-4 py-3"><Badge tone={STATUS_TONE[o.status] ?? 'neutral'}>{o.status_label}</Badge></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {meta.next_cursor ? (
          <div className="border-t border-border p-3 text-center">
            <Link href={`/dashboard/orders/history?${keep({ cursor: meta.next_cursor })}`} className="text-sm text-brand hover:underline">سفارش‌های قدیمی‌تر</Link>
          </div>
        ) : null}
      </Card>
    </div>
  );
}
