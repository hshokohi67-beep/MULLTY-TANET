import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { CalendarDays, ChevronLeft, ChevronRight, Coins, PieChart, ReceiptText } from 'lucide-react';
import { Card, CardHeader, StatTile } from '@cafe/ui';
import { formatMoney, formatNumber, gregorianToJalali, JALALI_MONTHS, jalaliDaysInMonth, jalaliToGregorian, todayIn, toPersianDigits } from '@cafe/locale';
import { MoneyShareBar } from '@/components/MoneyCharts';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { requireFeature } from '@/lib/billing';
import type { Expense, ExpenseCategory, ExpenseSummary } from '@/lib/operations-types';
import type { Branch, Tenant } from '@/lib/types';
import { CategoryManager, ExpenseList } from './ExpenseManager';

export const metadata: Metadata = { title: 'هزینه‌ها' };

type Month = { year: number; month: number };

function parseMonth(value: unknown, fallback: Month): Month {
  const m = typeof value === 'string' ? /^(\d{4})-(\d{1,2})$/.exec(value) : null;
  if (!m) return fallback;
  const month = Number(m[2]);

  return month >= 1 && month <= 12 ? { year: Number(m[1]), month } : fallback;
}

const shift = ({ year, month }: Month, by: number): Month => {
  const i = year * 12 + (month - 1) + by;

  return { year: Math.floor(i / 12), month: (i % 12) + 1 };
};
const key = (m: Month) => `${m.year}-${m.month}`;
const label = (m: Month) => `${JALALI_MONTHS[m.month - 1]} ${toPersianDigits(m.year)}`;

/** Running costs by Jalali month: rent, bills, repairs… feeding the profit widget. */
export default async function ExpensesPage({ searchParams }: PageProps<'/dashboard/expenses'>) {
  const { can } = await requireMembership();
  if (!can('expenses.manage')) redirect('/dashboard');
  await requireFeature('operations');
  const params = await searchParams;

  const tenant = (await api<{ data: Tenant }>('/tenant')).data;
  const today = todayIn(tenant.timezone);
  const current = gregorianToJalali(today);
  const month = parseMonth(params.m, { year: current.year, month: current.month });
  const from = jalaliToGregorian({ ...month, day: 1 });
  const to = jalaliToGregorian({ ...month, day: jalaliDaysInMonth(month.year, month.month) });
  const prev = shift(month, -1);
  const prevFrom = jalaliToGregorian({ ...prev, day: 1 });
  const isCurrent = month.year === current.year && month.month === current.month;
  // A month in progress is compared with the same days of the previous one, not all of it.
  const prevDays = jalaliDaysInMonth(prev.year, prev.month);
  const prevTo = jalaliToGregorian({ ...prev, day: isCurrent ? Math.min(current.day, prevDays) : prevDays });
  const next = shift(month, 1);

  const [{ data: expenses }, { data: summary }, { data: previous }, { data: categories }, { data: branches }] = await Promise.all([
    api<{ data: Expense[] }>(`/expenses?from=${from}&to=${to}`),
    api<{ data: ExpenseSummary }>(`/expenses/summary?from=${from}&to=${to}`),
    api<{ data: ExpenseSummary }>(`/expenses/summary?from=${prevFrom}&to=${prevTo}`),
    api<{ data: ExpenseCategory[] }>('/expense-categories'),
    api<{ data: Branch[] }>('/branches'),
  ]);

  // Days elapsed in this month (the whole month once it's over) for a fair daily average.
  const days = isCurrent ? current.day : jalaliDaysInMonth(month.year, month.month);
  const top = summary.categories[0];
  const parts = summary.categories.slice(0, 3).map((c) => ({ key: c.id, label: c.name, value: c.amount }));
  const rest = summary.categories.slice(3).reduce((s, c) => s + c.amount, 0);
  if (rest > 0) parts.push({ key: 'rest', label: 'سایر', value: rest });

  const nav = 'flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-surface hover:text-text';

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="هزینه‌ها" description="اجاره، قبوض، تعمیرات و هر خرجی جز خرید مواد؛ در ویجت «سود و زیان» از فروش کم می‌شود."
        actions={
          <nav aria-label="ماه" className="flex items-center gap-1 rounded-xl border border-border bg-surface-muted p-1">
            <Link href={`/dashboard/expenses?m=${key(prev)}`} className={nav} aria-label={`ماه قبل: ${label(prev)}`}><ChevronRight className="size-4" /></Link>
            <span className="flex min-w-32 items-center justify-center gap-1.5 px-2 text-sm font-semibold"><CalendarDays className="size-4 text-text-subtle" aria-hidden="true" />{label(month)}</span>
            {isCurrent ? <span className={`${nav} opacity-30`} aria-hidden="true"><ChevronLeft className="size-4" /></span> : (
              <Link href={`/dashboard/expenses?m=${key(next)}`} className={nav} aria-label={`ماه بعد: ${label(next)}`}><ChevronLeft className="size-4" /></Link>
            )}
          </nav>
        } />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile label={`هزینه‌ی ${JALALI_MONTHS[month.month - 1]}`} value={formatMoney(summary.total)} icon={<Coins />}
          delta={previous.total > 0 ? { ratio: summary.total / previous.total - 1, against: isCurrent ? `نسبت به ${toPersianDigits(Math.min(current.day, prevDays))} روز اول ${JALALI_MONTHS[prev.month - 1]}` : `نسبت به ${JALALI_MONTHS[prev.month - 1]}`, upIsGood: false } : undefined} />
        <StatTile label="تعداد" value={formatNumber(expenses.length)} icon={<ReceiptText />} hint="هزینه‌ی ثبت‌شده" />
        <StatTile label="میانگین روزانه" value={formatMoney(Math.round(summary.total / Math.max(1, days) / 10) * 10)} icon={<CalendarDays />} hint={isCurrent ? `در ${toPersianDigits(days)} روز گذشته` : 'در کل ماه'} />
        <StatTile label="بیشترین" value={top ? top.name : '—'} icon={<PieChart />} hint={top ? formatMoney(top.amount) : 'هنوز هزینه‌ای نیست'} />
      </div>

      <div className="grid items-start gap-6 lg:grid-cols-[1fr_20rem]">
        <ExpenseList expenses={expenses} categories={categories.filter((c) => c.is_active)} branches={branches.map((b) => ({ id: b.id, name: b.name }))}
          defaultDate={isCurrent ? today : to} monthLabel={label(month)} />

        <div className="flex flex-col gap-6">
          <Card>
            <CardHeader title="به تفکیک دسته" />
            <div className="p-5">
              <MoneyShareBar parts={parts} caption={`سهم دسته‌ها از هزینه‌های ${label(month)}`} empty="در این ماه هزینه‌ای ثبت نشده." />
            </div>
          </Card>
          <Card>
            <CardHeader title="دسته‌ها" />
            <CategoryManager categories={categories} />
          </Card>
        </div>
      </div>
    </div>
  );
}
