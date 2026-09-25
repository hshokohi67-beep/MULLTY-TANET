import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { ChevronRight } from 'lucide-react';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Ingredient } from '@/lib/inventory-types';
import type { Branch } from '@/lib/types';
import { StockCount } from './StockCount';

export const metadata: Metadata = { title: 'انبارگردانی' };

export default async function CountPage() {
  const { can } = await requireMembership();
  if (!can('inventory.manage')) redirect('/dashboard/inventory');

  const [{ data: ingredients }, { data: branches }] = await Promise.all([
    api<{ data: Ingredient[] }>('/inventory/ingredients?active=1'),
    api<{ data: Branch[] }>('/branches'),
  ]);

  return (
    <div className="flex flex-col gap-6">
      <Link href="/dashboard/inventory" className="inline-flex items-center gap-1 text-sm text-text-muted hover:text-text"><ChevronRight className="size-4" aria-hidden="true" />انبار</Link>
      <PageHeader title="انبارگردانی" description="موجودی واقعی را بشمارید و وارد کنید؛ اختلاف با دفتر به‌صورت «انبارگردانی» ثبت می‌شود. ردیف‌های خالی دست نمی‌خورند." />
      <StockCount ingredients={ingredients} branches={branches.map((b) => ({ id: b.id, name: b.name }))} />
    </div>
  );
}
