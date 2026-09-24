import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { Badge, Card, CardHeader, EmptyState, Ltr } from '@cafe/ui';
import { formatJalaliDate, formatJalaliDateTime, formatMoney, formatNumber } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Payment, PaymentSummary } from '@/lib/types';

export const metadata: Metadata = { title: 'پرداخت‌ها' };

const METHODS = [['', 'همه'], ['cash', 'نقدی'], ['card_pos', 'کارتخوان'], ['online', 'اینترنتی'], ['other', 'سایر']] as const;
const STATUSES = [['', 'همه'], ['paid', 'موفق'], ['pending', 'در انتظار'], ['failed', 'ناموفق'], ['expired', 'منقضی']] as const;
const STATUS_TONE = { paid: 'success', pending: 'info', failed: 'danger', expired: 'neutral' } as const;

function FilterChips({ name, options, current, other }: { name: string; options: readonly (readonly [string, string])[]; current: string; other: Record<string, string> }) {
  return (
    <div className="flex flex-wrap gap-2">
      {options.map(([value, label]) => {
        const query = new URLSearchParams(Object.entries({ ...other, [name]: value }).filter(([, v]) => v !== ''));
        const active = current === value;

        return (
          <Link
            key={value}
            href={`/dashboard/payments${query.size ? `?${query}` : ''}`}
            className={`rounded-full border px-3 py-1 text-sm ${active ? 'border-brand bg-brand-soft text-brand-strong' : 'border-border hover:bg-surface-muted'}`}
          >
            {label}
          </Link>
        );
      })}
    </div>
  );
}

export default async function PaymentsPage({ searchParams }: PageProps<'/dashboard/payments'>) {
  const { can } = await requireMembership();

  if (!can('payments.view')) {
    redirect('/dashboard');
  }

  const params = await searchParams;
  const method = typeof params.method === 'string' ? params.method : '';
  const status = typeof params.status === 'string' ? params.status : '';
  const query = new URLSearchParams(Object.entries({ method, status }).filter(([, v]) => v !== ''));

  const [{ data: payments }, { data: summary }] = await Promise.all([
    api<{ data: Payment[] }>(`/payments${query.size ? `?${query}` : ''}`),
    api<{ data: PaymentSummary }>('/payments/summary'),
  ]);
  const todayTotal = summary.methods.reduce((sum, m) => sum + m.amount - m.refunded, 0);

  return (
    <div className="flex flex-col gap-5">
      <PageHeader title="پرداخت‌ها" description="پرداخت‌های اینترنتی، نقدی و کارتخوان همه‌ی سفارش‌ها. پرداخت نقدی و بازگشت وجه را از صفحه‌ی هر سفارش ثبت کنید." />

      <Card>
        <CardHeader title={`دریافتی امروز (${formatJalaliDate(summary.date)})`} description={`خالص: ${formatMoney(todayTotal)}`} />
        <div className="grid grid-cols-2 gap-4 p-5 sm:grid-cols-3 lg:grid-cols-5">
          {summary.methods.map((m) => (
            <div key={m.method} className="rounded-md border border-border p-3">
              <p className="text-xs text-text-muted">{m.label}</p>
              <p className="mt-1 font-semibold">{formatMoney(m.amount)}</p>
              <p className="text-xs text-text-muted">
                {formatNumber(m.count)} پرداخت{m.refunded > 0 ? ` • بازگشت ${formatMoney(m.refunded)}` : ''}
              </p>
            </div>
          ))}
        </div>
      </Card>

      <div className="flex flex-col gap-3">
        <FilterChips name="method" options={METHODS} current={method} other={{ status }} />
        <FilterChips name="status" options={STATUSES} current={status} other={{ method }} />
      </div>

      {payments.length === 0 ? (
        <Card><EmptyState title="پرداختی پیدا نشد" description="با ثبت اولین پرداخت، فهرست اینجا نمایش داده می‌شود." /></Card>
      ) : (
        <Card>
          <ul className="divide-y divide-border">
            {payments.map((p) => (
              <li key={p.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                <div>
                  <p className="font-medium">
                    {p.order ? (
                      <Link href={`/dashboard/orders/${p.order.id}`} className="text-brand hover:underline">سفارش #{formatNumber(p.order.daily_number)}</Link>
                    ) : null}
                    {' • '}{p.method_label}
                  </p>
                  <p className="text-xs text-text-muted">
                    {formatJalaliDateTime(p.paid_at ?? p.created_at)}
                    {p.ref_id ? <> • کد پیگیری <Ltr>{p.ref_id}</Ltr></> : null}
                    {p.reference ? <> • رسید <Ltr>{p.reference}</Ltr></> : null}
                    {p.failure_message ? <> • {p.failure_message}</> : null}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  {p.refunded_amount > 0 ? <Badge tone="warning">بازگشت {formatMoney(p.refunded_amount)}</Badge> : null}
                  <span className="font-semibold">{formatMoney(p.amount)}</span>
                  <Badge tone={STATUS_TONE[p.status]}>{p.status_label}</Badge>
                </div>
              </li>
            ))}
          </ul>
        </Card>
      )}
    </div>
  );
}
