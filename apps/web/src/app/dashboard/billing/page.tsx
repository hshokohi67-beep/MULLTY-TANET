import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { CalendarClock, CreditCard, FileText, Lock, Package, Printer, Receipt, Sparkles } from 'lucide-react';
import { Alert, Badge, Card, CardHeader, cx, EmptyState, type Tone } from '@cafe/ui';
import { formatJalaliDate, formatMoney, formatNumber, toPersianDigits } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { FEATURE_PITCH, USAGE_LABELS, type Addon, type FeatureDef, type Invoice, type Plan, type Subscription } from '@/lib/billing-types';
import { PayInvoiceButton, PlanPicker, SubscriptionActions } from './BillingClient';

export const metadata: Metadata = { title: 'اشتراک و پرداخت' };

const STATE_TONE: Record<Subscription['state'], Tone> = { trial: 'info', active: 'success', grace: 'warning', read_only: 'danger' };
const INVOICE_TONE: Record<Invoice['status'], Tone> = { open: 'warning', paid: 'success', void: 'neutral' };
const INVOICE_LABEL: Record<Invoice['status'], string> = { open: 'در انتظار پرداخت', paid: 'پرداخت‌شده', void: 'باطل' };

/** The café's subscription: state, usage against limits, plans and add-ons, invoices. */
export default async function BillingPage({ searchParams }: PageProps<'/dashboard/billing'>) {
  const { can } = await requireMembership();
  if (!can('billing.manage')) redirect('/dashboard');
  const params = await searchParams;

  const [{ data: billing }, { data: catalog }, { data: invoices }] = await Promise.all([
    api<{ data: { subscription: Subscription; usage: Record<string, number>; open_invoice: Invoice | null } }>('/billing'),
    api<{ data: { plans: Plan[]; addons: Addon[]; features: FeatureDef[]; vat_rate: number } }>('/billing/plans'),
    api<{ data: Invoice[] }>('/billing/invoices'),
  ]);
  const s = billing.subscription;
  const feature = typeof params.feature === 'string' ? params.feature : null;
  const payment = typeof params.payment === 'string' ? params.payment : null;
  const pitch = feature ? FEATURE_PITCH[feature] : null;

  const end = s.ends_at;
  // Length of the running trial/period in days (a renewal can make it longer than one cycle).
  const periodStart = s.status === 'trialing' ? null : s.current_period_start;
  const total = periodStart && end ? Math.max(1, Math.round((Date.parse(end) - Date.parse(periodStart)) / 86_400_000)) : s.status === 'trialing' ? 14 : s.cycle === 'yearly' ? 365 : 30;
  const left = Math.min(total, s.days_left ?? 0);

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="اشتراک و پرداخت" description="پلن کافه، سقف‌ها، افزونه‌ها و صورت‌حساب‌ها؛ هیچ اطلاعاتی با تغییر پلن یا پایان اشتراک پاک نمی‌شود." />

      {payment === 'ok' ? <Alert tone="success" title="پرداخت انجام شد">اشتراک به‌روز شد. رسید در فهرست صورت‌حساب‌ها است.</Alert> : null}
      {payment === 'failed' ? <Alert tone="danger" title="پرداخت انجام نشد">اگر مبلغی از حساب شما کم شده، طی ۷۲ ساعت برمی‌گردد. دوباره تلاش کنید.</Alert> : null}
      {payment === 'cancelled' ? <Alert tone="warning">پرداخت نیمه‌کاره ماند؛ هر وقت خواستید دوباره اقدام کنید.</Alert> : null}
      {pitch ? (
        <Alert tone="info" title={`«${pitch.title}» در پلن فعلی نیست`}>
          <span className="inline-flex items-center gap-1.5"><Lock className="size-3.5" aria-hidden="true" />{pitch.text} با ارتقای پلن بلافاصله فعال می‌شود؛ اطلاعات قبلی‌تان هم سر جایش است.</span>
        </Alert>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-[3fr_2fr]">
        <Card>
          <div className="flex flex-col gap-5 p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="flex items-center gap-3">
                <span className="flex size-12 items-center justify-center rounded-2xl bg-brand-soft text-brand"><Sparkles className="size-6" aria-hidden="true" /></span>
                <div>
                  <p className="text-xs text-text-muted">پلن فعلی</p>
                  <p className="text-xl font-bold">{s.plan.name} <span className="text-sm font-normal text-text-muted">• {s.status === 'trialing' ? 'آزمایشی' : s.cycle === 'yearly' ? 'سالانه' : 'ماهانه'}</span></p>
                </div>
              </div>
              <Badge tone={STATE_TONE[s.state]} dot>{s.status === 'cancelled' && s.state === 'active' ? 'لغوشده تا پایان دوره' : s.state_label}</Badge>
            </div>

            <div>
              <div className="flex items-baseline justify-between text-sm">
                <span className="text-text-muted">
                  {s.state === 'read_only' ? 'پنل فقط‌خواندنی است' : s.state === 'grace' ? 'مهلت پرداخت تا فقط‌خواندنی شدن' : s.status === 'trialing' ? 'تا پایان دوره‌ی آزمایشی' : 'تا پایان دوره'}
                </span>
                <span className="font-semibold">{s.state === 'read_only' ? '—' : `${toPersianDigits(s.days_left ?? 0)} روز`}</span>
              </div>
              <div className="mt-2 h-2.5 overflow-hidden rounded-full bg-surface-muted">
                <div className={cx('h-full rounded-full', s.state === 'grace' || s.state === 'read_only' ? 'bg-danger' : left <= 5 ? 'bg-warning' : 'bg-brand')} style={{ width: `${s.state === 'read_only' ? 100 : Math.max(3, (left / total) * 100)}%` }} />
              </div>
              {end ? <p className="mt-1.5 text-xs text-text-subtle">{s.status === 'trialing' ? 'پایان آزمایشی' : 'پایان دوره'}: {formatJalaliDate(end)}</p> : null}
            </div>

            {s.addons.length ? (
              <div className="flex flex-wrap gap-1.5">
                {s.addons.map((a) => <Badge key={a.id} tone="brand"><Package className="size-3" aria-hidden="true" />{a.name}{a.quantity > 1 ? ` × ${toPersianDigits(a.quantity)}` : ''}</Badge>)}
              </div>
            ) : null}
            {s.scheduled ? <Alert tone="info">از پایان این دوره پلن به «{s.scheduled.plan.name}» تغییر می‌کند.</Alert> : null}

            {billing.open_invoice ? (
              <div className="flex flex-wrap items-center gap-3 rounded-xl border border-warning/40 bg-warning-soft px-4 py-3">
                <Receipt className="size-5 text-warning" aria-hidden="true" />
                <div className="flex-1 text-sm">
                  <p className="font-semibold">صورت‌حساب {billing.open_invoice.kind === 'renewal' ? 'تمدید' : ''} {toPersianDigits(billing.open_invoice.number)}</p>
                  <p className="text-text-muted">{formatMoney(billing.open_invoice.total)}{billing.open_invoice.due_at ? ` • سررسید ${formatJalaliDate(billing.open_invoice.due_at)}` : ''}</p>
                </div>
                <PayInvoiceButton id={billing.open_invoice.id} />
              </div>
            ) : null}
            <SubscriptionActions status={s.status} state={s.state} />
          </div>
        </Card>

        <Card>
          <CardHeader icon={<CalendarClock />} title="مصرف در برابر سقف" description="سفارش ماهانه فقط هشدار می‌دهد؛ سفارش مشتری هیچ‌وقت رد نمی‌شود." />
          <ul className="flex flex-col gap-4 p-5">
            {(['branches', 'staff', 'products', 'monthly_orders'] as const).map((key) => {
              const used = billing.usage[key] ?? 0;
              const limit = s.features[key];
              const ratio = typeof limit === 'number' && limit > 0 ? used / limit : 0;

              return (
                <li key={key}>
                  <p className="flex items-baseline justify-between text-sm">
                    <span>{USAGE_LABELS[key]}</span>
                    <span className="tabular text-text-muted"><strong className={cx('text-text', ratio >= 1 && 'text-danger', ratio >= 0.8 && ratio < 1 && 'text-warning')}>{formatNumber(used)}</strong> از {typeof limit === 'number' ? formatNumber(limit) : 'نامحدود'}</span>
                  </p>
                  <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-muted">
                    <div className={cx('h-full rounded-full', ratio >= 1 ? 'bg-danger' : ratio >= 0.8 ? 'bg-warning' : 'bg-brand')} style={{ width: `${typeof limit === 'number' ? Math.min(100, Math.max(ratio > 0 ? 3 : 0, ratio * 100)) : 4}%` }} />
                  </div>
                </li>
              );
            })}
          </ul>
        </Card>
      </div>

      <PlanPicker plans={catalog.plans} addons={catalog.addons} features={catalog.features} current={{ planId: s.plan.id, cycle: s.cycle, status: s.status, addons: s.addons }} highlight={feature} />

      <Card>
        <CardHeader icon={<FileText />} title="صورت‌حساب‌ها" />
        {invoices.length === 0 ? <EmptyState icon={<CreditCard />} title="هنوز صورت‌حسابی ندارید" description="با انتخاب پلن، صورت‌حساب رسمی با مالیات بر ارزش افزوده صادر می‌شود." /> : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[40rem] text-sm">
              <thead className="bg-surface-muted/60 text-xs text-text-muted">
                <tr>
                  <th className="px-4 py-2.5 text-start font-medium">شماره</th>
                  <th className="px-4 py-2.5 text-start font-medium">تاریخ</th>
                  <th className="px-4 py-2.5 text-start font-medium">شرح</th>
                  <th className="px-4 py-2.5 text-end font-medium">مبلغ</th>
                  <th className="px-4 py-2.5 text-start font-medium">وضعیت</th>
                  <th className="px-4 py-2.5" />
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {invoices.map((i) => (
                  <tr key={i.id}>
                    <td className="tabular px-4 py-3 font-medium" dir="ltr" style={{ textAlign: 'start' }}>{toPersianDigits(i.number)}</td>
                    <td className="px-4 py-3 text-text-muted">{formatJalaliDate(i.created_at)}</td>
                    <td className="px-4 py-3">پلن {i.plan.name} • {i.cycle === 'yearly' ? 'سالانه' : 'ماهانه'}{i.kind === 'renewal' ? ' (تمدید)' : ''}</td>
                    <td className="tabular px-4 py-3 text-end font-semibold">{formatMoney(i.total)}</td>
                    <td className="px-4 py-3"><Badge tone={INVOICE_TONE[i.status]} dot>{INVOICE_LABEL[i.status]}</Badge></td>
                    <td className="px-4 py-3 text-end">
                      <span className="inline-flex items-center gap-1">
                        {i.status === 'open' ? <PayInvoiceButton id={i.id} small /> : null}
                        <Link href={`/print/invoice/${i.id}`} target="_blank" aria-label={`چاپ صورت‌حساب ${i.number}`} className="flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted hover:text-text"><Printer className="size-4" /></Link>
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  );
}
