import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Branch, Category } from '@/lib/types';
import { BulkPriceForm } from './BulkPriceForm';

export const metadata: Metadata = { title: 'تغییر گروهی قیمت' };

export default async function BulkPricesPage() {
  const { can } = await requireMembership();

  if (!can('prices.manage')) {
    redirect('/dashboard/menu');
  }

  const [{ data: categories }, { data: branches }] = await Promise.all([
    api<{ data: Category[] }>('/catalog/categories'),
    api<{ data: Branch[] }>('/branches'),
  ]);

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="تغییر گروهی قیمت"
        description="اول پیش‌نمایش را ببینید، بعد اعمال کنید. همه‌ی تغییرها در تاریخچه‌ی قیمت ثبت می‌شوند."
      />
      <BulkPriceForm categories={categories} branches={branches} />
    </div>
  );
}
