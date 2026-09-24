import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { requireMembership } from '@/lib/auth';
import { BranchForm } from '../BranchForm';

export const metadata: Metadata = { title: 'شعبه‌ی جدید' };

export default async function NewBranchPage() {
  const { can } = await requireMembership();

  if (!can('branches.manage')) {
    redirect('/dashboard/branches');
  }

  return (
    <>
      <PageHeader title="شعبه‌ی جدید" description="بعد از ایجاد، ساعات کاری شعبه را تعیین کنید." />
      <BranchForm />
    </>
  );
}
