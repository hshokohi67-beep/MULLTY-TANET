import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { Card, EmptyState, SelectField } from '@cafe/ui';
import { formatJalaliDate, formatMoney, formatNumber, formatPhone, JALALI_MONTHS, toPersianDigits } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { hasFeature } from '@/lib/billing';
import type { CustomerRow, LoyaltyProgram } from '@/lib/types';

export const metadata: Metadata = { title: 'مشتریان' };

export default async function CustomersPage({ searchParams }: PageProps<'/dashboard/customers'>) {
  const { can } = await requireMembership();

  if (!can('customers.view')) {
    redirect('/dashboard');
  }

  const params = await searchParams;
  const q = typeof params.q === 'string' ? params.q : '';
  const tierId = typeof params.tier_id === 'string' ? params.tier_id : '';
  const birthMonth = typeof params.birth_month === 'string' ? params.birth_month : '';
  const query = new URLSearchParams(Object.entries({ q, tier_id: tierId, birth_month: birthMonth }).filter(([, v]) => v !== ''));

  const [{ data: customers }, tiers] = await Promise.all([
    api<{ data: CustomerRow[] }>(`/customers${query.size ? `?${query}` : ''}`),
    can('loyalty.manage') && await hasFeature('loyalty') ? api<{ data: LoyaltyProgram }>('/loyalty/program').then((r) => r.data.tiers) : Promise.resolve([]),
  ]);
  const filtered = query.size > 0;

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="مشتریان"
        description="اعضای باشگاه با موجودی کیف پول، امتیاز و سطح"
        actions={can('customers.export') ? (
          <a href={`/dashboard/customers/export${query.size ? `?${query}` : ''}`} className="inline-flex h-10 items-center rounded-md border border-border-strong bg-surface px-4 text-sm hover:bg-surface-muted">
            خروجی اکسل (CSV)
          </a>
        ) : null}
      />

      <form className="flex flex-wrap items-end gap-3" role="search" aria-label="جستجوی مشتری">
        <div className="min-w-48 flex-1">
          <label htmlFor="customer-search" className="mb-1.5 block text-sm font-medium">جستجو</label>
          <input
            id="customer-search"
            name="q"
            defaultValue={q}
            placeholder="نام یا شماره موبایل…"
            className="h-10 w-full rounded-md border border-border-strong bg-surface px-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none"
          />
        </div>
        {tiers.length > 0 ? (
          <div className="w-40">
            <SelectField label="سطح" name="tier_id" defaultValue={tierId}>
              <option value="">همه</option>
              {tiers.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </SelectField>
          </div>
        ) : null}
        <div className="w-40">
          <SelectField label="ماه تولد" name="birth_month" defaultValue={birthMonth}>
            <option value="">همه</option>
            {JALALI_MONTHS.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
          </SelectField>
        </div>
        <button type="submit" className="h-10 rounded-md border border-border-strong bg-surface px-4 text-sm hover:bg-surface-muted">اعمال</button>
      </form>

      <Card>
        {customers.length === 0 ? (
          <EmptyState
            title={filtered ? 'مشتری‌ای با این مشخصات پیدا نشد' : 'هنوز مشتری‌ای ندارید'}
            description={filtered ? 'عبارت جستجو یا فیلترها را تغییر دهید.' : 'مشتریان با اولین ورود با شماره موبایل در منوی آنلاین عضو باشگاه می‌شوند.'}
          />
        ) : (
          <ul className="divide-y divide-border">
            {customers.map((c) => (
              <li key={c.id}>
                <Link href={`/dashboard/customers/${c.id}`} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-surface-muted">
                  <div>
                    <p className="font-medium">
                      {c.name ?? 'بدون نام'}
                      {c.tier ? (
                        <span className="ms-2 rounded-full px-2 py-0.5 text-xs text-white" style={{ backgroundColor: c.tier.color ?? '#94A3B8' }}>{c.tier.name}</span>
                      ) : null}
                    </p>
                    <p className="text-xs text-text-muted">
                      {formatPhone(c.phone)}
                      {c.birth_month && c.birth_day ? ` • تولد ${toPersianDigits(c.birth_day)} ${JALALI_MONTHS[c.birth_month - 1]}` : ''}
                      {c.last_order_at ? ` • آخرین سفارش ${formatJalaliDate(c.last_order_at)}` : ''}
                    </p>
                  </div>
                  <div className="flex gap-4 text-end text-xs text-text-muted">
                    <span>کیف پول<br /><strong className={`text-sm ${c.wallet_balance < 0 ? 'text-danger' : 'text-text'}`}>{formatMoney(c.wallet_balance)}</strong></span>
                    <span>امتیاز<br /><strong className="text-sm text-text">{formatNumber(c.points)}</strong></span>
                    <span>سفارش‌ها<br /><strong className="text-sm text-text">{formatNumber(c.orders_count)}</strong></span>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}
