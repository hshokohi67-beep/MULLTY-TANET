import type { Metadata } from 'next';
import Link from 'next/link';
import { Badge, Card, EmptyState } from '@cafe/ui';
import { formatClock, formatPhone, IRANIAN_WEEK } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Branch } from '@/lib/types';

export const metadata: Metadata = { title: 'شعبه‌ها' };

export default async function BranchesPage() {
  const { can } = await requireMembership();
  const { data: branches } = await api<{ data: Branch[] }>('/branches');
  const canManage = can('branches.manage');

  return (
    <>
      <PageHeader
        title="شعبه‌ها و ساعات کاری"
        description="هر شعبه منو، میزها، محدوده‌ی ارسال و ساعات کاری خودش را دارد."
        actions={canManage ? <Link href="/dashboard/branches/new" className="rounded-md bg-brand px-4 py-2 text-sm font-medium text-on-brand hover:bg-brand-strong">افزودن شعبه</Link> : null}
      />

      {branches.length === 0 ? (
        <Card>
          <EmptyState title="هنوز شعبه‌ای ثبت نشده" description="برای شروع دریافت سفارش، اولین شعبه را اضافه کنید." />
        </Card>
      ) : (
        <ul className="grid gap-4 sm:grid-cols-2">
          {branches.map((branch) => {
            // Iranian week order: Saturday first.
            const hours = [...(branch.opening_hours ?? [])].sort((a, b) => IRANIAN_WEEK.indexOf(a.weekday) - IRANIAN_WEEK.indexOf(b.weekday));

            return (
              <li key={branch.id}>
                <Card className="flex h-full flex-col gap-3 p-5">
                  <div className="flex items-start justify-between gap-2">
                    <h2 className="font-semibold">{branch.name}</h2>
                    <Badge tone={branch.is_active ? 'success' : 'neutral'}>{branch.is_active ? 'فعال' : 'غیرفعال'}</Badge>
                  </div>
                  <p className="text-sm text-text-muted">{[branch.city, branch.address].filter(Boolean).join('، ') || 'آدرس ثبت نشده'}</p>
                  {branch.phone ? <p className="text-sm">{formatPhone(branch.phone)}</p> : null}
                  <p className="text-xs text-text-muted">
                    {hours.length === 0
                      ? 'ساعات کاری تعیین نشده'
                      : `${hours[0].weekday_label} ${formatClock(hours[0].opens_at)} تا ${formatClock(hours[0].closes_at)}${hours.length > 1 ? ' و …' : ''}`}
                  </p>
                  <Link href={`/dashboard/branches/${branch.id}`} className="mt-auto text-sm font-medium text-brand hover:underline">
                    {canManage ? 'ویرایش و ساعات کاری' : 'مشاهده'}
                  </Link>
                </Card>
              </li>
            );
          })}
        </ul>
      )}
    </>
  );
}
