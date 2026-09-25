import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { ChevronRight } from 'lucide-react';
import { Badge, Card, CardHeader } from '@cafe/ui';
import { formatJalaliDate, formatJalaliDateTime, formatMoney, formatNumber } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api, ApiError } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { formatQty, type Ingredient, type PurchaseOrder, type Supplier } from '@/lib/inventory-types';
import type { Branch } from '@/lib/types';
import { STATUS_TONE } from '../status';
import { PurchaseEditor } from '../PurchaseEditor';
import { PaymentForm, PurchaseActions } from './PurchaseActions';

export const metadata: Metadata = { title: 'سفارش خرید' };

export default async function PurchasePage({ params }: PageProps<'/dashboard/purchases/[id]'>) {
  const { can } = await requireMembership();
  if (!can('purchasing.manage')) redirect('/dashboard');
  const { id } = await params;

  let po: PurchaseOrder;
  try {
    po = (await api<{ data: PurchaseOrder }>(`/inventory/purchases/${encodeURIComponent(id)}`)).data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  const back = <Link href="/dashboard/purchases" className="inline-flex items-center gap-1 text-sm text-text-muted hover:text-text"><ChevronRight className="size-4" aria-hidden="true" />خرید</Link>;

  if (po.status === 'draft') {
    const [{ data: suppliers }, { data: branches }, { data: ingredients }] = await Promise.all([
      api<{ data: Supplier[] }>('/inventory/suppliers'),
      api<{ data: Branch[] }>('/branches'),
      api<{ data: Ingredient[] }>('/inventory/ingredients?active=1'),
    ]);

    return (
      <div className="flex flex-col gap-6">
        {back}
        <PageHeader title={`پیش‌نویس خرید #${formatNumber(po.number)}`} description="بعد از نهایی شدن، «ثبت سفارش» را بزنید؛ یا اگر جنس رسیده، مستقیم «تحویل گرفتم»."
          actions={<PurchaseActions po={po} />} />
        <PurchaseEditor purchase={po} suppliers={suppliers.map((s) => ({ id: s.id, name: s.name }))} branches={branches.map((b) => ({ id: b.id, name: b.name }))} ingredients={ingredients} />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      {back}
      <PageHeader title={`خرید #${formatNumber(po.number)} از ${po.supplier?.name ?? ''}`}
        description={`${po.branch?.name ?? ''} • ثبت ${formatJalaliDate(po.created_at)}${po.received_at ? ` • تحویل ${formatJalaliDateTime(po.received_at)}` : ''}`}
        actions={<><Badge tone={STATUS_TONE[po.status]} dot>{po.status_label}</Badge><PurchaseActions po={po} /></>} />

      <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <Card>
          <CardHeader title="اقلام" />
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="border-y border-border text-xs text-text-muted">
                <tr>
                  <th scope="col" className="px-4 py-2.5 text-start font-medium">ماده</th>
                  <th scope="col" className="px-4 py-2.5 text-start font-medium">سفارش</th>
                  <th scope="col" className="px-4 py-2.5 text-start font-medium">تحویل</th>
                  <th scope="col" className="px-4 py-2.5 text-start font-medium">قیمت</th>
                  <th scope="col" className="px-4 py-2.5 text-start font-medium">جمع</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {po.items?.map((i) => (
                  <tr key={i.id}>
                    <td className="px-4 py-3 font-medium">{i.ingredient.name}</td>
                    <td className="tabular px-4 py-3">{formatQty(i.quantity, i.ingredient.unit)}</td>
                    <td className={`tabular px-4 py-3 ${i.received_quantity >= i.quantity ? 'text-success' : i.received_quantity > 0 ? 'text-warning' : 'text-text-muted'}`}>{formatQty(i.received_quantity, i.ingredient.unit)}</td>
                    <td className="tabular px-4 py-3 text-text-muted">{formatMoney(i.ingredient.unit === 'pcs' ? Math.round(i.unit_price / 1000) : i.unit_price)} / {i.ingredient.unit === 'g' ? 'کیلو' : i.ingredient.unit === 'ml' ? 'لیتر' : 'عدد'}</td>
                    <td className="tabular px-4 py-3 font-semibold">{formatMoney(i.line_total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {po.note ? <p className="border-t border-border px-4 py-3 text-sm text-text-muted">{po.note}</p> : null}
        </Card>

        <Card>
          <CardHeader title="حساب" />
          <dl className="flex flex-col gap-2 px-5 pb-4 text-sm">
            <div className="flex justify-between"><dt className="text-text-muted">مبلغ کل</dt><dd className="tabular font-semibold">{formatMoney(po.total)}</dd></div>
            <div className="flex justify-between"><dt className="text-text-muted">پرداخت‌شده</dt><dd className="tabular text-success">{formatMoney(po.paid_total)}</dd></div>
            <div className="flex justify-between border-t border-border pt-2"><dt className="font-medium">مانده</dt><dd className={`tabular font-bold ${po.balance_due ? 'text-warning' : 'text-success'}`}>{formatMoney(po.balance_due)}</dd></div>
          </dl>
          {po.payments?.length ? (
            <ul className="flex flex-col gap-1.5 border-t border-border px-5 py-3 text-xs text-text-muted">
              {po.payments.map((p) => <li key={p.id} className="flex justify-between"><span>{formatJalaliDate(p.paid_at)} • {p.method_label}</span><span className="tabular">{formatMoney(p.amount)}</span></li>)}
            </ul>
          ) : null}
          {po.status !== 'cancelled' && po.balance_due > 0 ? <div className="border-t border-border p-4"><PaymentForm id={po.id} due={po.balance_due} /></div> : null}
        </Card>
      </div>
    </div>
  );
}
