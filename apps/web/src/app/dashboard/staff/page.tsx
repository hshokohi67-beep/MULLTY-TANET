import type { Metadata } from 'next';
import Link from 'next/link';
import type { ReactNode } from 'react';
import { redirect } from 'next/navigation';
import { CalendarClock, Clock, UserCheck, Users } from 'lucide-react';
import { cx, StatTile } from '@cafe/ui';
import { addDays, formatMoney, formatNumber, gregorianToJalali, JALALI_MONTHS, jalaliDaysInMonth, jalaliToGregorian, todayIn, toPersianDigits } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { formatHours, formatMinutes, type AttendanceRecord, type Employee, type Payroll, type Shift } from '@/lib/operations-types';
import type { Branch, TeamMember, Tenant } from '@/lib/types';
import { AttendanceBoard } from './AttendanceBoard';
import { EmployeeList } from './EmployeeList';
import { PeriodNav } from './PeriodNav';
import { ShiftBoard } from './ShiftBoard';

export const metadata: Metadata = { title: 'کارکنان' };

const TABS = [
  { key: 'schedule', label: 'برنامه‌ی شیفت' },
  { key: 'attendance', label: 'حضور و غیاب' },
  { key: 'payroll', label: 'کارکرد و دستمزد' },
  { key: 'team', label: 'کارکنان' },
] as const;
type Tab = (typeof TABS)[number]['key'];

const DATE = /^\d{4}-\d{2}-\d{2}$/;

/** Saturday on or before a date (the Iranian week starts on Saturday). */
function weekStart(date: string): string {
  const day = new Date(`${date}T12:00:00Z`).getUTCDay(); // 0 Sun … 6 Sat

  return addDays(date, -((day + 1) % 7));
}

const shortDate = (date: string) => {
  const j = gregorianToJalali(date);

  return `${toPersianDigits(j.day)} ${JALALI_MONTHS[j.month - 1]}`;
};

/** Staff: the team, a weekly shift plan, who clocked in (and late), and hours × rate for payroll. */
export default async function StaffPage({ searchParams }: PageProps<'/dashboard/staff'>) {
  const { can } = await requireMembership();
  if (!can('staff.manage')) redirect('/dashboard');
  const params = await searchParams;
  const tab: Tab = TABS.some((t) => t.key === params.tab) ? (params.tab as Tab) : 'schedule';

  const tenant = (await api<{ data: Tenant }>('/tenant')).data;
  const tz = tenant.timezone;
  const today = todayIn(tz);

  const [{ data: employees }, { data: branches }, { data: todayAttendance }] = await Promise.all([
    api<{ data: Employee[] }>('/staff/employees'),
    api<{ data: Branch[] }>('/branches'),
    api<{ data: AttendanceRecord[] }>(`/staff/attendance?from=${today}&to=${today}`),
  ]);
  const branchOptions = branches.map((b) => ({ id: b.id, name: b.name }));
  const active = employees.filter((e) => e.is_active);
  const onShift = todayAttendance.filter((r) => r.clock_out_at === null);
  const lateToday = todayAttendance.filter((r) => r.late_minutes > 0).length;

  const tabHref = (t: Tab) => `/dashboard/staff?tab=${t}`;
  let body: ReactNode = null;

  if (tab === 'schedule') {
    const week = weekStart(typeof params.week === 'string' && DATE.test(params.week) ? params.week : today);
    const end = addDays(week, 6);
    const { data: shifts } = await api<{ data: Shift[] }>(`/staff/shifts?from=${week}&to=${end}`);
    body = (
      <>
        <PeriodNav label={`${shortDate(week)} تا ${shortDate(end)}`} prev={`/dashboard/staff?tab=schedule&week=${addDays(week, -7)}`}
          next={`/dashboard/staff?tab=schedule&week=${addDays(week, 7)}`} current={week === weekStart(today) ? null : '/dashboard/staff?tab=schedule'} currentLabel="این هفته" />
        <ShiftBoard employees={active} shifts={shifts} weekStart={week} today={today} timezone={tz} />
      </>
    );
  } else if (tab === 'attendance') {
    const day = typeof params.date === 'string' && DATE.test(params.date) && params.date <= today ? params.date : today;
    const records = day === today ? todayAttendance : (await api<{ data: AttendanceRecord[] }>(`/staff/attendance?from=${day}&to=${day}`)).data;
    body = (
      <>
        <PeriodNav label={day === today ? `امروز، ${shortDate(day)}` : shortDate(day)} prev={`/dashboard/staff?tab=attendance&date=${addDays(day, -1)}`}
          next={day < today ? `/dashboard/staff?tab=attendance&date=${addDays(day, 1)}` : null} current={day === today ? null : '/dashboard/staff?tab=attendance'} currentLabel="امروز" />
        <AttendanceBoard records={records} employees={active} day={day} isToday={day === today} timezone={tz} />
      </>
    );
  } else if (tab === 'payroll') {
    const now = gregorianToJalali(today);
    const m = typeof params.m === 'string' ? /^(\d{4})-(\d{1,2})$/.exec(params.m) : null;
    const month = m && Number(m[2]) >= 1 && Number(m[2]) <= 12 ? { year: Number(m[1]), month: Number(m[2]) } : { year: now.year, month: now.month };
    const from = jalaliToGregorian({ ...month, day: 1 });
    const to = jalaliToGregorian({ ...month, day: jalaliDaysInMonth(month.year, month.month) });
    const idx = month.year * 12 + month.month - 1;
    const at = (i: number) => `${Math.floor(i / 12)}-${(i % 12) + 1}`;
    const isNow = month.year === now.year && month.month === now.month;
    const { data: payroll } = await api<{ data: Payroll }>(`/staff/payroll?from=${from}&to=${to}`);
    body = (
      <>
        <PeriodNav label={`${JALALI_MONTHS[month.month - 1]} ${toPersianDigits(month.year)}`} prev={`/dashboard/staff?tab=payroll&m=${at(idx - 1)}`}
          next={isNow ? null : `/dashboard/staff?tab=payroll&m=${at(idx + 1)}`} current={isNow ? null : '/dashboard/staff?tab=payroll'} currentLabel="این ماه" />
        <PayrollTable payroll={payroll} employees={employees} />
      </>
    );
  } else {
    let members: TeamMember[] = [];
    if (can('team.view')) members = (await api<{ data: TeamMember[] }>('/team')).data;
    body = <EmployeeList employees={employees} branches={branchOptions} members={members.filter((m) => m.status === 'active').map((m) => ({ id: m.user.id, name: m.user.name, role: m.roles[0]?.name ?? null }))} />;
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="کارکنان" description="برنامه‌ی شیفت، ورود و خروج، تأخیر و هزینه‌ی دستمزد؛ کارکنانی که حساب کاربری دارند خودشان از نوار بالا ورود و خروج می‌زنند." />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile label="کارکنان فعال" value={formatNumber(active.length)} icon={<Users />} hint={`${formatNumber(active.filter((e) => e.user_id).length)} نفر با حساب کاربری`} />
        <StatTile label="الان سر کار" value={formatNumber(onShift.length)} icon={<UserCheck />} hint={onShift.length ? onShift.map((r) => r.employee.name).slice(0, 3).join('، ') : 'کسی ورود نزده'} />
        <StatTile label="کارکرد امروز" value={formatHours(todayAttendance.reduce((s, r) => s + r.minutes, 0))} icon={<Clock />} hint="ساعت:دقیقه" />
        <StatTile label="تأخیر امروز" value={formatNumber(lateToday)} icon={<CalendarClock />} hint={lateToday ? 'بیش از ۱۰ دقیقه پس از شروع شیفت' : 'همه به‌موقع'} />
      </div>

      <div className="flex flex-col gap-4">
        <nav aria-label="بخش‌ها" className="flex max-w-full gap-1 self-start overflow-x-auto rounded-full bg-surface-muted p-1">
          {TABS.map((t) => (
            <Link key={t.key} href={tabHref(t.key)} aria-current={tab === t.key ? 'page' : undefined}
              className={cx('whitespace-nowrap rounded-full px-3.5 py-1.5 text-sm', tab === t.key ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text')}>{t.label}</Link>
          ))}
        </nav>
        {body}
      </div>
    </div>
  );
}

function PayrollTable({ payroll, employees }: { payroll: Payroll; employees: Employee[] }) {
  const byId = new Map(employees.map((e) => [e.id, e]));

  if (payroll.rows.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-border bg-surface px-6 py-14 text-center">
        <p className="font-semibold">در این ماه کارکردی ثبت نشده</p>
        <p className="mt-1 text-sm text-text-muted">با ورود و خروج کارکنان (یا ثبت دستی در «حضور و غیاب») ساعت کار و دستمزد اینجا جمع می‌شود.</p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-border bg-surface shadow-[var(--shadow-sm)]">
      <div className="overflow-x-auto">
        <table className="w-full min-w-[40rem] text-sm">
          <thead className="bg-surface-muted/60 text-xs text-text-muted">
            <tr>
              <th className="px-4 py-2.5 text-start font-medium">نام</th>
              <th className="px-4 py-2.5 text-start font-medium">نرخ</th>
              <th className="px-4 py-2.5 text-start font-medium">شیفت</th>
              <th className="px-4 py-2.5 text-start font-medium">کارکرد</th>
              <th className="px-4 py-2.5 text-start font-medium">تأخیر</th>
              <th className="px-4 py-2.5 text-end font-medium">دستمزد</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {payroll.rows.map((r) => {
              const e = byId.get(r.employee_id);

              return (
                <tr key={r.employee_id}>
                  <td className="px-4 py-3">
                    <p className="font-medium">{r.name}{r.open ? <span className="ms-2 inline-block size-2 rounded-full bg-success align-middle" title="الان سر کار" /> : null}</p>
                    {r.position ? <p className="text-xs text-text-muted">{r.position}</p> : null}
                  </td>
                  <td className="tabular px-4 py-3 text-text-muted">{e ? (e.pay_type === 'hourly' ? `${formatMoney(e.rate)} / ساعت` : `${formatMoney(e.rate)} / ماه`) : '—'}</td>
                  <td className="tabular px-4 py-3">{formatNumber(r.shifts)}</td>
                  <td className="tabular px-4 py-3">{formatMinutes(r.minutes)}</td>
                  <td className="px-4 py-3">{r.late ? <span className="rounded-full bg-warning-soft px-2 py-0.5 text-xs font-medium text-warning">{formatNumber(r.late)} بار</span> : <span className="text-text-subtle">—</span>}</td>
                  <td className="tabular px-4 py-3 text-end font-semibold">{formatMoney(r.cost)}</td>
                </tr>
              );
            })}
          </tbody>
          <tfoot className="border-t-2 border-border bg-surface-muted/40 font-semibold">
            <tr>
              <td className="px-4 py-3" colSpan={3}>جمع</td>
              <td className="tabular px-4 py-3">{formatMinutes(payroll.total_minutes)}</td>
              <td />
              <td className="tabular px-4 py-3 text-end">{formatMoney(payroll.total_cost)}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <p className="border-t border-border px-4 py-2.5 text-[11px] text-text-subtle">حقوق ماهانه بر اساس ساعت کار واقعی و ساعت استاندارد ماه (پیش‌فرض ۱۹۲ ساعت) سرشکن شده؛ برای فیش حقوق مبنا بگیرید، نه جایگزین.</p>
    </div>
  );
}
