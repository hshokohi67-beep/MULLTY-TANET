import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { Alert } from '@cafe/ui';
import { PageHeader } from '@/components/PageHeader';
import { api, ApiError } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Branch } from '@/lib/types';
import { BranchForm } from '../BranchForm';
import { OpeningHoursEditor } from './OpeningHoursEditor';

export const metadata: Metadata = { title: 'ویرایش شعبه' };

export default async function BranchPage({ params, searchParams }: PageProps<'/dashboard/branches/[id]'>) {
  const [{ id }, query] = await Promise.all([params, searchParams]);
  const { can } = await requireMembership();

  let branch: Branch;
  try {
    branch = (await api<{ data: Branch }>(`/branches/${encodeURIComponent(id)}`)).data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  const canManage = can('branches.manage');

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={branch.name} description="مشخصات، آدرس و ساعات کاری شعبه" />
      {query.created === '1' ? <Alert tone="success">شعبه ایجاد شد. حالا ساعات کاری آن را تعیین کنید.</Alert> : null}
      <OpeningHoursEditor branchId={branch.id} initial={branch.opening_hours ?? []} readOnly={!canManage} />
      <BranchForm branch={branch} readOnly={!canManage} />
    </div>
  );
}
