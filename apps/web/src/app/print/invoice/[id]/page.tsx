import type { Metadata } from 'next';
import { notFound, redirect } from 'next/navigation';
import { formatJalaliDate, formatMoney, toPersianDigits } from '@cafe/locale';
import { api, ApiError } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Invoice } from '@/lib/billing-types';
import type { Tenant } from '@/lib/types';
import { PrintControls } from '../../reports/PrintControls';

export const metadata: Metadata = { title: 'صورت‌حساب' };

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;

/** A4 subscription invoice (platform → café), printable or saved as PDF. */
export default async function PrintInvoicePage({ params }: PageProps<'/print/invoice/[id]'>) {
  const { can } = await requireMembership();
  if (!can('billing.manage')) redirect('/dashboard');
  const { id } = await params;
  if (!ULID.test(id)) notFound();

  let invoice: Invoice;
  try {
    invoice = (await api<{ data: Invoice }>(`/billing/invoices/${id}`)).data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }
  const tenant = (await api<{ data: Tenant }>('/tenant')).data;
  const status = invoice.status === 'paid' ? 'پرداخت‌شده' : invoice.status === 'open' ? 'در انتظار پرداخت' : 'باطل';

  return (
    <div className="print-page min-h-screen bg-bg">
      <PrintControls />
      <main id="main" className="mx-auto flex max-w-3xl flex-col gap-6 px-6 py-8">
        <header className="flex items-start justify-between gap-4 border-b-2 border-text pb-4">
          <div>
            <h1 className="text-2xl font-bold">صورت‌حساب اشتراک</h1>
            <p className="mt-1 text-sm text-text-muted">کافه‌یار • نرم‌افزار مدیریت کافه</p>
          </div>
          <dl className="grid grid-cols-[auto_auto] gap-x-4 gap-y-1 text-sm">
            <dt className="text-text-muted">شماره</dt><dd className="tabular font-semibold" dir="ltr" style={{ textAlign: 'start' }}>{toPersianDigits(invoice.number)}</dd>
            <dt className="text-text-muted">تاریخ صدور</dt><dd>{formatJalaliDate(invoice.created_at)}</dd>
            <dt className="text-text-muted">وضعیت</dt><dd className="font-semibold">{status}</dd>
          </dl>
        </header>

        <section className="grid grid-cols-2 gap-4 text-sm">
          <div className="rounded-xl border border-border p-4">
            <p className="text-xs text-text-muted">خریدار</p>
            <p className="mt-1 font-semibold">{tenant.name}</p>
          </div>
          <div className="rounded-xl border border-border p-4">
            <p className="text-xs text-text-muted">دوره‌ی خدمت</p>
            <p className="mt-1 font-semibold">
              {invoice.period_start && invoice.period_end ? `${formatJalaliDate(invoice.period_start)} تا ${formatJalaliDate(invoice.period_end)}` : 'پس از پرداخت تعیین می‌شود'}
            </p>
          </div>
        </section>

        <table className="w-full text-sm">
          <thead className="border-b border-border text-xs text-text-muted">
            <tr><th className="py-2 text-start font-medium">شرح</th><th className="py-2 text-end font-medium">مبلغ</th></tr>
          </thead>
          <tbody className="divide-y divide-border">
            {invoice.lines.map((l) => <tr key={l.label}><td className="py-2.5">{l.label}</td><td className="tabular py-2.5 text-end">{formatMoney(l.amount)}</td></tr>)}
          </tbody>
          <tfoot className="border-t-2 border-border">
            <tr><td className="pt-3 text-text-muted">جمع</td><td className="tabular pt-3 text-end">{formatMoney(invoice.subtotal)}</td></tr>
            {invoice.credit ? <tr><td className="pt-1 text-text-muted">اعتبار روزهای باقی‌مانده</td><td className="tabular pt-1 text-end">−{formatMoney(invoice.credit)}</td></tr> : null}
            <tr><td className="pt-1 text-text-muted">مالیات بر ارزش افزوده ({toPersianDigits(invoice.vat_rate)}٪)</td><td className="tabular pt-1 text-end">{formatMoney(invoice.vat)}</td></tr>
            <tr><td className="pt-3 text-base font-bold">مبلغ کل</td><td className="tabular pt-3 text-end text-base font-bold">{formatMoney(invoice.total)}</td></tr>
          </tfoot>
        </table>

        {invoice.paid_at ? (
          <p className="rounded-xl bg-success-soft px-4 py-3 text-sm text-success">
            پرداخت‌شده در {formatJalaliDate(invoice.paid_at)}{invoice.paid_via === 'transfer' ? ' با حواله‌ی بانکی' : invoice.paid_via === 'gateway' ? ' از درگاه پرداخت' : ''}
            {invoice.reference ? <> • پیگیری: <span dir="ltr">{toPersianDigits(invoice.reference)}</span></> : null}
          </p>
        ) : null}
        <footer className="border-t border-border pt-3 text-center text-xs text-text-subtle">مبالغ به تومان است.</footer>
      </main>
    </div>
  );
}
