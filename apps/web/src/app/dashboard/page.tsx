import type { Metadata } from 'next';
import Link from 'next/link';
import type { ReactNode } from 'react';
import { ArrowUpLeft, CircleAlert, Info, TriangleAlert } from 'lucide-react';
import { formatJalaliLong } from '@cafe/locale';
import { LiveRefresh } from '@/components/LiveRefresh';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Branch, Branding, TeamMember, Tenant } from '@/lib/types';
import type { LayoutWidget } from '@/app/actions/dashboard-layout';
import { DashboardGrid, type CatalogItem } from './DashboardGrid';
import { SetupChecklist, type SetupStep } from './SetupChecklist';
import * as W from './widgets/Widgets';

export const metadata: Metadata = { title: 'پیشخوان' };

const RANGES = [
  { key: 'today', label: 'امروز' },
  { key: 'yesterday', label: 'دیروز' },
  { key: '7d', label: '۷ روز' },
  { key: '30d', label: '۳۰ روز' },
] as const;

/** Widgets whose data comes from the combined overview call; the rest fetch /dashboard/widgets/{key}. */
const OVERVIEW_WIDGETS = new Set(['kpis', 'sales_chart', 'live', 'top_products', 'payment_mix', 'customers']);

export default async function DashboardHome({ searchParams }: PageProps<'/dashboard'>) {
  const { user, can } = await requireMembership();
  const params = await searchParams;
  const range = RANGES.some((r) => r.key === params.range) ? (params.range as string) : 'today';
  const branchId = typeof params.branch === 'string' ? params.branch : '';
  const query = new URLSearchParams({ range, ...(branchId ? { branch_id: branchId } : {}) });

  const [tenant, branding, branches, team, menuTotal, { data: layout }, { data: overview }] = await Promise.all([
    api<{ data: Tenant }>('/tenant').then((r) => r.data),
    api<{ data: Branding }>('/tenant/branding').then((r) => r.data),
    can('branches.view') ? api<{ data: Branch[] }>('/branches').then((r) => r.data) : Promise.resolve([] as Branch[]),
    can('team.view') ? api<{ data: TeamMember[] }>('/team').then((r) => r.data) : Promise.resolve(null),
    can('catalog.view') ? api<{ meta: { total: number } }>('/catalog/products?per_page=1').then((r) => r.meta.total) : Promise.resolve(null),
    api<{ data: { widgets: LayoutWidget[]; is_default: boolean; catalog: CatalogItem[] } }>('/dashboard/layout'),
    api<{ data: W.OverviewData }>(`/dashboard/overview?${query}`),
  ]);

  // Each extra widget on this user's dashboard loads its own data, in parallel.
  const extraKeys = layout.widgets.map((w) => w.key).filter((k) => !OVERVIEW_WIDGETS.has(k));
  const extra = Object.fromEntries(await Promise.all(extraKeys.map(async (key) => {
    try {
      return [key, (await api<{ data: unknown }>(`/dashboard/widgets/${key}?${query}`)).data] as const;
    } catch {
      return [key, null] as const; // one failing widget must not take the dashboard down
    }
  })));

  const rangeLabel = RANGES.find((r) => r.key === range)?.label ?? '';
  const ctx: W.WidgetContext = { range, rangeLabel, isToday: range === 'today', userId: user.id, can };

  /* eslint-disable @typescript-eslint/no-explicit-any -- widget payloads are typed inside each widget */
  const render: Record<string, (data: any) => ReactNode> = {
    kpis: () => <W.KpisWidget data={overview} ctx={ctx} />,
    sales_chart: () => <W.SalesChartWidget data={overview} ctx={ctx} />,
    live: () => <W.LiveWidget data={overview} />,
    top_products: () => <W.TopProductsWidget data={overview} ctx={ctx} />,
    payment_mix: () => <W.PaymentMixWidget data={overview} ctx={ctx} />,
    customers: () => <W.CustomersWidget data={overview} ctx={ctx} />,
    goal: (d) => <W.GoalWidget data={d} ctx={ctx} />,
    channel_mix: (d) => <W.ChannelMixWidget data={d} ctx={ctx} />,
    heatmap: (d) => <W.HeatmapWidget data={d} />,
    tables_now: (d) => <W.TablesNowWidget data={d} />,
    kitchen_speed: (d) => <W.KitchenSpeedWidget data={d} ctx={ctx} />,
    cancellations: (d) => <W.CancellationsWidget data={d} ctx={ctx} />,
    branches: (d) => <W.BranchesWidget data={d} ctx={ctx} />,
    payment_health: (d) => <W.PaymentHealthWidget data={d} ctx={ctx} />,
    at_risk: (d) => <W.AtRiskWidget data={d} />,
    club_liability: (d) => <W.ClubLiabilityWidget data={d} ctx={ctx} />,
    discounts: (d) => <W.DiscountsWidget data={d} ctx={ctx} />,
    shift_notes: (d) => <W.ShiftNotesWidget data={d} ctx={ctx} />,
    stories: (d) => <W.StoriesWidget data={d} />,
  };
  /* eslint-enable @typescript-eslint/no-explicit-any */

  const nodes: Record<string, ReactNode> = {};
  for (const { key } of layout.widgets) {
    if (OVERVIEW_WIDGETS.has(key)) nodes[key] = render[key]?.(overview);
    else if (extra[key]) nodes[key] = render[key]?.(extra[key]);
  }

  const steps: SetupStep[] = [
    { done: (menuTotal ?? 0) > 0, title: 'اولین آیتم‌های منو را اضافه کنید', hint: 'با «افزودن سریع» فقط نام و قیمت کافی است.', href: '/dashboard/menu' },
    { done: Boolean(branding.logo_url), title: 'لوگوی کافه را بارگذاری کنید', hint: 'در منوی آنلاین و فاکتورها نمایش داده می‌شود.', href: '/dashboard/settings' },
    { done: branches.some((b) => b.address), title: 'آدرس شعبه را کامل کنید', hint: 'برای محاسبه‌ی محدوده‌ی ارسال لازم است.', href: '/dashboard/branches' },
    { done: branches.some((b) => (b.opening_hours?.length ?? 0) > 0), title: 'ساعات کاری را تعیین کنید', hint: 'مشتری می‌بیند کافه باز است یا نه.', href: '/dashboard/branches' },
    { done: (team?.length ?? 0) > 1, title: 'همکاران خود را اضافه کنید', hint: 'صندوق‌دار، آشپزخانه و سالن‌دار هرکدام دسترسی خودشان را دارند.', href: '/dashboard/team' },
  ];

  const link = (extraParams: Record<string, string>) => `/dashboard?${new URLSearchParams(Object.entries({ range, branch: branchId, ...extraParams }).filter(([, v]) => v !== ''))}`;
  const firstName = user.name.split(/\s+/)[0];
  const hour = Number(new Intl.DateTimeFormat('en-GB', { hour: 'numeric', hourCycle: 'h23', timeZone: tenant.timezone }).format(new Date()));
  const greeting = hour < 12 ? 'صبح بخیر' : hour < 17 ? 'روز بخیر' : 'عصر بخیر';

  return (
    <div className="flex flex-col gap-6">
      <LiveRefresh endpoint="/dashboard/orders/live" seconds={15} />

      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-sm text-text-muted">{formatJalaliLong(new Date(), tenant.timezone, true)}</p>
          <h1 className="mt-1 text-2xl font-bold">{greeting}، {firstName}</h1>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <nav aria-label="بازه‌ی زمانی" className="flex gap-1 rounded-full bg-surface-muted p-1">
            {RANGES.map((r) => (
              <Link key={r.key} href={link({ range: r.key })} aria-current={range === r.key ? 'page' : undefined}
                className={`rounded-full px-3.5 py-1.5 text-sm transition-colors ${range === r.key ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text'}`}>
                {r.label}
              </Link>
            ))}
          </nav>
          {branches.length > 1 ? (
            <nav aria-label="شعبه" className="flex gap-1 rounded-full bg-surface-muted p-1">
              {[{ id: '', name: 'همه‌ی شعبه‌ها' }, ...branches].map((b) => (
                <Link key={b.id} href={link({ branch: b.id })} aria-current={branchId === b.id ? 'page' : undefined}
                  className={`rounded-full px-3 py-1.5 text-sm ${branchId === b.id ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text'}`}>
                  {b.name}
                </Link>
              ))}
            </nav>
          ) : null}
        </div>
      </div>

      {overview.alerts.length > 0 ? (
        <section aria-label="نیازمند توجه" className="grid gap-2 md:grid-cols-2">
          {overview.alerts.map((a) => {
            const Icon = a.severity === 'danger' ? CircleAlert : a.severity === 'warning' ? TriangleAlert : Info;
            const tone = a.severity === 'danger' ? 'border-danger/30 bg-danger-soft text-danger' : a.severity === 'warning' ? 'border-warning/30 bg-warning-soft text-warning' : 'border-info/25 bg-info-soft text-info';

            return (
              <Link key={a.type} href={a.href} className={`group flex items-center gap-3 rounded-xl border px-4 py-3 transition-shadow hover:shadow-[var(--shadow-md)] ${tone}`}>
                <Icon className="size-5 shrink-0" aria-hidden="true" />
                <span className="flex-1 text-sm font-medium text-text">{a.title}</span>
                <span className="flex items-center gap-1 text-xs font-semibold">بررسی<ArrowUpLeft className="size-3.5 transition-transform group-hover:-translate-x-0.5 group-hover:-translate-y-0.5" aria-hidden="true" /></span>
              </Link>
            );
          })}
        </section>
      ) : null}

      <SetupChecklist steps={steps} />

      <DashboardGrid layout={layout.widgets} catalog={layout.catalog} nodes={nodes} isDefault={layout.is_default} />
    </div>
  );
}
