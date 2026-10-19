import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { Card, EmptyState } from '@cafe/ui';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { hasFeature } from '@/lib/billing';
import type { Category, Discount, LoyaltyProgram, Product, Tenant } from '@/lib/types';
import { DiscountEditor } from './DiscountEditor';

export const metadata: Metadata = { title: 'تخفیف‌ها' };

export default async function DiscountsPage({ searchParams }: PageProps<'/dashboard/discounts'>) {
  const { can } = await requireMembership();

  if (!can('discounts.manage')) {
    redirect('/dashboard');
  }

  const [{ data: discounts }, tiers, { data: products }, { data: categories }, { data: tenant }] = await Promise.all([
    api<{ data: Discount[] }>('/discounts'),
    can('loyalty.manage') && await hasFeature('loyalty') ? api<{ data: LoyaltyProgram }>('/loyalty/program').then((r) => r.data.tiers.map((t) => ({ id: t.id, name: t.name }))) : Promise.resolve([]),
    api<{ data: Product[] }>('/catalog/products?per_page=200'),
    api<{ data: Category[] }>('/catalog/categories'),
    api<{ data: Tenant }>('/tenant'),
  ]);
  // «ساخت تخفیف» from the occasions calendar: a new discount named and dated for the occasion.
  const q = await searchParams;
  const isDay = (v: unknown): v is string => typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v);
  const prefill = typeof q.name === 'string' && isDay(q.from) && isDay(q.to) ? { name: q.name.slice(0, 120), from: q.from, to: q.to } : undefined;
  const catalog = {
    products: products.map((p) => ({ id: p.id, name: p.name })),
    categories: categories.map((c) => ({ id: c.id, name: c.name })),
    timezone: tenant.timezone,
  };

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="تخفیف‌ها"
        description="تخفیف بدون کد خودکار روی سفارش‌های واجد شرایط اعمال می‌شود؛ تخفیف کددار را مشتری هنگام پرداخت وارد می‌کند. در هر سفارش یک تخفیف (بیشترین) اعمال می‌شود؛ تخفیف‌های «نمایش روی منو» جدا، روی قیمت خود محصول حساب می‌شوند."
      />
      <DiscountEditor tiers={tiers} catalog={catalog} prefill={prefill} />
      {discounts.length === 0 ? (
        <Card><EmptyState title="هنوز تخفیفی ندارید" description="مثلاً «ساعت خوش» عصرها یا کد «یلدا» برای شب یلدا." /></Card>
      ) : (
        discounts.map((d) => <DiscountEditor key={d.id} discount={d} tiers={tiers} catalog={catalog} />)
      )}
    </div>
  );
}
