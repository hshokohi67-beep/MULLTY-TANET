import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { Card, CardHeader } from '@cafe/ui';
import { formatJalaliDateTime, formatMoney, formatNumber, formatPhone } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api, ApiError } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { CustomerDetail, Order, Payment } from '@/lib/types';
import { OrderCard, OrderTimeline } from '../OrderBoard';
import { OrderPayments } from './OrderPayments';

export const metadata: Metadata = { title: 'جزئیات سفارش' };

export default async function OrderPage({ params }: PageProps<'/dashboard/orders/[id]'>) {
  const { id } = await params;
  const { can } = await requireMembership();

  let order: Order;
  try {
    order = (await api<{ data: Order }>(`/orders/${encodeURIComponent(id)}`)).data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  const payments = can('payments.view')
    ? (await api<{ data: Payment[] }>(`/orders/${encodeURIComponent(id)}/payments`)).data
    : null;
  const walletBalance = order.customer_id && can('customers.view')
    ? (await api<{ data: CustomerDetail }>(`/customers/${order.customer_id}`)).data.club.wallet_balance
    : null;
  const address = order.address;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={`سفارش #${formatNumber(order.daily_number)}`}
        description={<>
          {order.status_label} • ثبت {formatJalaliDateTime(order.placed_at)} ·{' '}
          <Link href="/dashboard/orders" className="text-brand hover:underline">بازگشت به سفارش‌ها</Link>
        </>}
      />

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <OrderCard order={order} canManage={can('orders.manage')} />

        <div className="flex flex-col gap-6">
          <Card>
            <CardHeader title="صورت‌حساب" />
            <dl className="grid grid-cols-2 gap-y-2 p-5 text-sm">
              <dt className="text-text-muted">جمع آیتم‌ها</dt><dd className="text-end">{formatMoney(order.subtotal)}</dd>
              {order.discount_total > 0 ? <>
                <dt className="text-text-muted">تخفیف{order.discount ? ` (${order.discount.name})` : ''}</dt>
                <dd className="text-end text-success">−{formatMoney(order.discount_total)}</dd>
              </> : null}
              {order.type === 'delivery' ? <>
                <dt className="text-text-muted">هزینه‌ی ارسال</dt>
                <dd className="text-end">{order.delivery_fee === 0 ? 'رایگان' : formatMoney(order.delivery_fee)}</dd>
              </> : null}
              <dt className="font-semibold">مبلغ کل</dt><dd className="text-end font-semibold">{formatMoney(order.total)}</dd>
            </dl>
          </Card>

          {payments ? (
            <OrderPayments order={order} payments={payments} canRecord={can('payments.record')} canRefund={can('payments.refund')} walletBalance={walletBalance} />
          ) : null}

          {order.contact_name || order.contact_phone || address ? (
            <Card>
              <CardHeader title="مشتری و تحویل" />
              <div className="flex flex-col gap-1 p-5 text-sm">
                {order.contact_name ? (
                  <p>{order.customer_id && can('customers.view') ? <Link href={`/dashboard/customers/${order.customer_id}`} className="text-brand hover:underline">{order.contact_name}</Link> : order.contact_name}</p>
                ) : null}
                {order.contact_phone ? <p><a href={`tel:${order.contact_phone}`} className="text-brand hover:underline">{formatPhone(order.contact_phone)}</a></p> : null}
                {address ? (
                  <p className="text-text-muted">
                    {[address.city, address.district, address.address].filter(Boolean).join('، ')}
                    {address.building_number ? `، پلاک ${address.building_number}` : ''}
                    {address.floor ? `، طبقه ${address.floor}` : ''}
                    {address.unit ? `، واحد ${address.unit}` : ''}
                  </p>
                ) : null}
              </div>
            </Card>
          ) : null}

          <Card>
            <CardHeader title="تاریخچه" />
            <div className="p-5"><OrderTimeline order={order} /></div>
          </Card>
        </div>
      </div>
    </div>
  );
}
