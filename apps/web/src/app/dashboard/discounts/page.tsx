import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { Card, EmptyState } from '@cafe/ui';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { hasFeature } from '@/lib/billing';
import type { Discount, LoyaltyProgram } from '@/lib/types';
import { DiscountEditor } from './DiscountEditor';

export const metadata: Metadata = { title: 'تخفیف‌ها' };

export default async function DiscountsPage() {
  const { can } = await requireMembership();

  if (!can('discounts.manage')) {
    redirect('/dashboard');
  }

  const [{ data: discounts }, tiers] = await Promise.all([
    api<{ data: Discount[] }>('/discounts'),
    can('loyalty.manage') && await hasFeature('loyalty') ? api<{ data: LoyaltyProgram }>('/loyalty/program').then((r) => r.data.tiers.map((t) => ({ id: t.id, name: t.name }))) : Promise.resolve([]),
  ]);

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="تخفیف‌ها"
        description="تخفیف بدون کد خودکار روی سفارش‌های واجد شرایط اعمال می‌شود؛ تخفیف کددار را مشتری هنگام پرداخت وارد می‌کند. در هر سفارش فقط یک تخفیف (بیشترین) اعمال می‌شود."
      />
      <DiscountEditor tiers={tiers} />
      {discounts.length === 0 ? (
        <Card><EmptyState title="هنوز تخفیفی ندارید" description="مثلاً «ساعت خوش» عصرها یا کد «یلدا» برای شب یلدا." /></Card>
      ) : (
        discounts.map((d) => <DiscountEditor key={d.id} discount={d} tiers={tiers} />)
      )}
    </div>
  );
}
