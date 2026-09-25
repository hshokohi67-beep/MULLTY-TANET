import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import type { ReactNode } from 'react';
import { cx } from '@cafe/ui';
import { addDays, todayIn } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { jalaliShort, presets, type BranchRow, type CustomersReport, type HoursReport, type InventoryReport, type ProductsReport, type Summary } from '@/lib/report-types';
import type { Branch, Tenant } from '@/lib/types';
import { ExportMenu } from './ExportMenu';
import { ReportFilters } from './ReportFilters';
import { BranchesSection, CustomersSection, HoursSection, InventorySection, ProductsSection, SummarySection } from './sections';

export const metadata: Metadata = { title: 'گزارش‌ها' };

const TABS = [
  { key: 'summary', label: 'خلاصه و سود' },
  { key: 'products', label: 'محصولات' },
  { key: 'hours', label: 'روز و ساعت' },
  { key: 'branches', label: 'شعبه‌ها' },
  { key: 'customers', label: 'مشتریان' },
  { key: 'inventory', label: 'انبار' },
] as const;
type Tab = (typeof TABS)[number]['key'];

const DATE = /^\d{4}-\d{2}-\d{2}$/;
const one = (v: string | string[] | undefined) => (typeof v === 'string' ? v : undefined);

/** Reports over any Jalali period, with comparison, branch filter and Excel/CSV/PDF export. */
export default async function ReportsPage({ searchParams }: PageProps<'/dashboard/reports'>) {
  const { can } = await requireMembership();
  if (!can('reports.view')) redirect('/dashboard');
  const params = await searchParams;

  const tenant = (await api<{ data: Tenant }>('/tenant')).data;
  const today = todayIn(tenant.timezone);
  const rawFrom = one(params.from);
  const rawTo = one(params.to);
  const to = rawTo && DATE.test(rawTo) && rawTo <= today ? rawTo : today;
  const from = rawFrom && DATE.test(rawFrom) && rawFrom <= to ? rawFrom : addDays(to, -29);
  const compare = ['previous', 'last_year', 'none'].includes(one(params.compare) ?? '') ? (one(params.compare) as string) : 'previous';
  const branchId = one(params.branch) ?? '';
  const tab: Tab = TABS.some((t) => t.key === params.tab) ? (params.tab as Tab) : 'summary';
  const sort = ['revenue', 'quantity', 'margin'].includes(one(params.sort) ?? '') ? (one(params.sort) as string) : 'revenue';

  const state = { from, to, compare, branch: branchId };
  const href = (extra: Record<string, string>) => `/dashboard/reports?${new URLSearchParams(Object.entries({ ...state, tab, ...extra }).filter(([, v]) => v !== ''))}`;
  const query = new URLSearchParams(Object.entries({ from, to, compare, branch_id: branchId }).filter(([, v]) => v !== '')).toString();

  const branches = (await api<{ data: Branch[] }>('/branches')).data.map((b) => ({ id: b.id, name: b.name }));

  let body: ReactNode;
  if (tab === 'products') {
    const { data } = await api<{ data: ProductsReport }>(`/reports/products?${query}&sort=${sort}`);
    const sortLinks = (
      <nav aria-label="مرتب‌سازی" className="flex gap-1 rounded-lg bg-surface-muted p-0.5 text-xs">
        {([['revenue', 'فروش'], ['quantity', 'تعداد'], ['margin', 'سود']] as const).map(([k, l]) => (
          <Link key={k} href={href({ sort: k })} aria-current={sort === k ? 'true' : undefined}
            className={cx('rounded-md px-2.5 py-1', sort === k ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text')}>{l}</Link>
        ))}
      </nav>
    );
    body = <ProductsSection data={data} sortLinks={sortLinks} />;
  } else if (tab === 'hours') {
    body = <HoursSection data={(await api<{ data: HoursReport }>(`/reports/hours?${query}`)).data} />;
  } else if (tab === 'branches') {
    const { data } = await api<{ data: { branches: BranchRow[]; stale: boolean } }>(`/reports/branches?${query}`);
    body = <BranchesSection rows={data.branches} stale={data.stale} />;
  } else if (tab === 'customers') {
    body = <CustomersSection data={(await api<{ data: CustomersReport }>(`/reports/customers?${query}`)).data} />;
  } else if (tab === 'inventory') {
    body = <InventorySection data={(await api<{ data: InventoryReport }>(`/reports/inventory?${query}`)).data} />;
  } else {
    body = <SummarySection data={(await api<{ data: Summary }>(`/reports/summary?${query}`)).data} compare={compare} />;
  }

  const title = from === to ? jalaliShort(from, true) : `${jalaliShort(from, from.slice(0, 4) !== to.slice(0, 4))} تا ${jalaliShort(to, true)}`;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="گزارش‌ها" description={`${title}${branchId ? ` • ${branches.find((b) => b.id === branchId)?.name ?? ''}` : ' • همه‌ی شعبه‌ها'}`}
        actions={<ExportMenu query={query} printHref={`/print/reports?${query}`} />} />

      <ReportFilters presets={presets(today)} from={from} to={to} today={today} compare={compare} branch={branchId} branches={branches} tab={tab} />

      <nav aria-label="بخش‌های گزارش" className="flex max-w-full gap-1 self-start overflow-x-auto rounded-full bg-surface-muted p-1">
        {TABS.map((t) => (
          <Link key={t.key} href={href({ tab: t.key })} aria-current={tab === t.key ? 'page' : undefined}
            className={cx('whitespace-nowrap rounded-full px-3.5 py-1.5 text-sm', tab === t.key ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text')}>{t.label}</Link>
        ))}
      </nav>

      {body}
    </div>
  );
}
