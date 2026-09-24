import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { Badge, Card, CardHeader, Ltr } from '@cafe/ui';
import { formatJalaliDate, formatJalaliDateTime, formatMoney, formatNumber, formatPhone, JALALI_MONTHS, toPersianDigits } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api, ApiError } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { CustomerDetail, LedgerRow } from '@/lib/types';
import { AdjustmentForm, CustomerProfileForm } from './CustomerForms';

export const metadata: Metadata = { title: 'مشتری' };

function Ledger({ title, rows, unit }: { title: string; rows: LedgerRow[]; unit: 'money' | 'points' }) {
  const show = (n: number) => (unit === 'money' ? formatMoney(Math.abs(n)) : formatNumber(Math.abs(n)));

  return (
    <Card>
      <CardHeader title={title} />
      {rows.length === 0 ? (
        <p className="px-5 py-4 text-sm text-text-muted">هنوز تراکنشی ثبت نشده است.</p>
      ) : (
        <ul className="divide-y divide-border text-sm">
          {rows.map((r) => (
            <li key={r.id} className="flex items-center justify-between gap-3 px-5 py-2.5">
              <div>
                <p>{r.description ?? r.type_label}</p>
                <p className="text-xs text-text-muted">{r.type_label} • {formatJalaliDateTime(r.created_at)}</p>
              </div>
              <div className="text-end">
                <p className={r.amount < 0 ? 'text-danger' : 'text-success'}>{r.amount < 0 ? '−' : '+'}{show(r.amount)}</p>
                <p className="text-xs text-text-muted">مانده {unit === 'money' ? formatMoney(r.balance_after) : formatNumber(r.balance_after)}</p>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

export default async function CustomerPage({ params }: PageProps<'/dashboard/customers/[id]'>) {
  const { id } = await params;
  const { can } = await requireMembership();

  if (!can('customers.view')) {
    redirect('/dashboard');
  }

  let customer: CustomerDetail;
  try {
    customer = (await api<{ data: CustomerDetail }>(`/customers/${encodeURIComponent(id)}`)).data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  const [{ data: wallet }, { data: points }] = await Promise.all([
    api<{ data: LedgerRow[] }>(`/customers/${customer.id}/wallet-transactions`),
    api<{ data: LedgerRow[] }>(`/customers/${customer.id}/points-transactions`),
  ]);
  const club = customer.club;
  const progress = club.next_tier ? Math.min(100, Math.round((club.lifetime_spend / club.next_tier.min_spend) * 100)) : 100;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={customer.name ?? 'مشتری بدون نام'}
        description={<>
          <Ltr>{formatPhone(customer.phone)}</Ltr> • عضو از {formatJalaliDate(customer.created_at)}
          {customer.birth_month && customer.birth_day ? ` • تولد ${toPersianDigits(customer.birth_day)} ${JALALI_MONTHS[customer.birth_month - 1]}` : ''}
          {' • '}<Link href="/dashboard/customers" className="text-brand hover:underline">همه‌ی مشتریان</Link>
        </>}
      />

      <div className="grid gap-4 sm:grid-cols-4">
        <Card className="p-4">
          <p className="text-xs text-text-muted">کیف پول</p>
          <p className={`mt-1 text-lg font-bold ${club.wallet_balance < 0 ? 'text-danger' : ''}`}>{formatMoney(club.wallet_balance)}</p>
          {club.wallet_balance < 0 ? <p className="text-xs text-danger">برگشت کش‌بک پس از مصرف؛ تا جبران، پرداخت با کیف پول ممکن نیست.</p> : null}
        </Card>
        <Card className="p-4">
          <p className="text-xs text-text-muted">امتیاز</p>
          <p className="mt-1 text-lg font-bold">{formatNumber(club.points)}</p>
          <p className="text-xs text-text-muted">ارزش {formatMoney(club.points_value)}</p>
        </Card>
        <Card className="p-4">
          <p className="text-xs text-text-muted">سطح</p>
          <p className="mt-1 text-lg font-bold">{club.tier ? <Badge tone="brand">{club.tier.name}</Badge> : '—'}</p>
          {club.next_tier ? (
            <>
              <div className="mt-2 h-1.5 rounded-full bg-surface-muted" role="progressbar" aria-valuenow={progress} aria-valuemin={0} aria-valuemax={100} aria-label={`پیشرفت تا ${club.next_tier.name}`}>
                <div className="h-1.5 rounded-full bg-brand" style={{ width: `${progress}%` }} />
              </div>
              <p className="mt-1 text-xs text-text-muted">{formatMoney(club.next_tier.remaining)} تا {club.next_tier.name}</p>
            </>
          ) : null}
        </Card>
        <Card className="p-4">
          <p className="text-xs text-text-muted">مجموع خرید</p>
          <p className="mt-1 text-lg font-bold">{formatMoney(club.lifetime_spend)}</p>
          <p className="text-xs text-text-muted">{formatNumber(customer.orders_count)} سفارش • کد معرف <Ltr>{club.referral_code}</Ltr></p>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <div className="flex flex-col gap-6">
          <CustomerProfileForm customer={customer} readOnly={!can('customers.manage')} />
          {can('wallet.adjust') ? (
            <Card>
              <CardHeader title="اصلاح دستی" description="هر تغییر با دلیل و نام شما در گزارش رویدادها ثبت می‌شود." />
              <div className="flex flex-col gap-4 p-5">
                <AdjustmentForm customerId={customer.id} kind="wallet" />
                <AdjustmentForm customerId={customer.id} kind="points" />
              </div>
            </Card>
          ) : null}
          <Card>
            <CardHeader title="سفارش‌های اخیر" />
            {customer.recent_orders.length === 0 ? (
              <p className="px-5 py-4 text-sm text-text-muted">هنوز سفارشی ثبت نکرده است.</p>
            ) : (
              <ul className="divide-y divide-border text-sm">
                {customer.recent_orders.map((o) => (
                  <li key={o.id}>
                    <Link href={`/dashboard/orders/${o.id}`} className="flex items-center justify-between px-5 py-2.5 hover:bg-surface-muted">
                      <span>#{formatNumber(o.daily_number)} • {o.type_label} • {formatJalaliDate(o.placed_at)}</span>
                      <span className="flex items-center gap-2">{formatMoney(o.total)} <Badge>{o.status_label}</Badge></span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>
        <div className="flex flex-col gap-6">
          <Ledger title="گردش کیف پول" rows={wallet} unit="money" />
          <Ledger title="گردش امتیاز" rows={points} unit="points" />
        </div>
      </div>
    </div>
  );
}
