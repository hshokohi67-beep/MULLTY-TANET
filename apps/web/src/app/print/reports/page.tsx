import type { Metadata } from 'next';
import { formatJalaliDateTime, todayIn, addDays } from '@cafe/locale';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { requireFeature } from '@/lib/billing';
import { jalaliShort, type BranchRow, type ProductsReport, type Summary } from '@/lib/report-types';
import type { Tenant } from '@/lib/types';
import { BranchesSection, ProductsSection, SummarySection } from '@/app/dashboard/reports/sections';
import { redirect } from 'next/navigation';
import { PrintControls } from './PrintControls';

export const metadata: Metadata = { title: 'گزارش چاپی' };

const DATE = /^\d{4}-\d{2}-\d{2}$/;

/** A4 print layout of the report (summary, top products, branches); "Save as PDF" keeps the Persian font. */
export default async function PrintReportPage({ searchParams }: PageProps<'/print/reports'>) {
  const { can, membership } = await requireMembership();
  if (!can('reports.view')) redirect('/dashboard');
  await requireFeature('reports');
  const params = await searchParams;
  const tenant = (await api<{ data: Tenant }>('/tenant')).data;
  const today = todayIn(tenant.timezone);
  const to = typeof params.to === 'string' && DATE.test(params.to) ? params.to : today;
  const from = typeof params.from === 'string' && DATE.test(params.from) ? params.from : addDays(to, -29);
  const compare = typeof params.compare === 'string' ? params.compare : 'previous';
  const branchId = typeof params.branch_id === 'string' ? params.branch_id : '';
  const query = new URLSearchParams(Object.entries({ from, to, compare, branch_id: branchId }).filter(([, v]) => v !== '')).toString();

  const [{ data: summary }, { data: products }, { data: branches }] = await Promise.all([
    api<{ data: Summary }>(`/reports/summary?${query}`),
    api<{ data: ProductsReport }>(`/reports/products?${query}`),
    api<{ data: { branches: BranchRow[]; stale: boolean } }>(`/reports/branches?${query}`),
  ]);

  return (
    <div className="print-page min-h-screen bg-bg">
      <PrintControls />
      <main id="main" className="mx-auto flex max-w-4xl flex-col gap-6 px-6 py-8">
        <header className="flex items-end justify-between gap-4 border-b-2 border-text pb-4">
          <div>
            <p className="text-sm text-text-muted">{membership.tenant.name}</p>
            <h1 className="text-2xl font-bold">گزارش فروش و سود</h1>
            <p className="mt-1 text-sm">{jalaliShort(from, true)} تا {jalaliShort(to, true)}{branchId ? ` • ${branches.branches.find((b) => b.id === branchId)?.name ?? ''}` : ' • همه‌ی شعبه‌ها'}</p>
          </div>
          <p className="text-xs text-text-muted">تهیه‌شده {formatJalaliDateTime(new Date(), tenant.timezone)}</p>
        </header>
        <SummarySection data={summary} compare={compare} print />
        <section className="print-break">
          <h2 className="mb-3 text-lg font-bold">محصولات</h2>
          <ProductsSection data={products} limit={25} />
        </section>
        {branches.branches.length > 1 && !branchId ? (
          <section className="print-break">
            <h2 className="mb-3 text-lg font-bold">شعبه‌ها</h2>
            <BranchesSection rows={branches.branches} stale={false} />
          </section>
        ) : null}
        <footer className="border-t border-border pt-3 text-center text-xs text-text-subtle">کافه‌یار • مبالغ به تومان</footer>
      </main>
    </div>
  );
}
