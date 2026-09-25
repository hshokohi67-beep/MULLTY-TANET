import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { ChevronRight } from 'lucide-react';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Ingredient, Supplier } from '@/lib/inventory-types';
import type { Branch } from '@/lib/types';
import { PurchaseEditor } from '../PurchaseEditor';

export const metadata: Metadata = { title: 'سفارش خرید جدید' };

export default async function NewPurchasePage() {
  const { can } = await requireMembership();
  if (!can('purchasing.manage')) redirect('/dashboard');

  const [{ data: suppliers }, { data: branches }, { data: ingredients }] = await Promise.all([
    api<{ data: Supplier[] }>('/inventory/suppliers'),
    api<{ data: Branch[] }>('/branches'),
    api<{ data: Ingredient[] }>('/inventory/ingredients?active=1'),
  ]);

  return (
    <div className="flex flex-col gap-6">
      <Link href="/dashboard/purchases" className="inline-flex items-center gap-1 text-sm text-text-muted hover:text-text"><ChevronRight className="size-4" aria-hidden="true" />خرید</Link>
      <PageHeader title="سفارش خرید جدید" description="مقدار و قیمت را با همان واحدی بنویسید که از فروشنده می‌خرید (کیلو، لیتر، بسته…)." />
      <PurchaseEditor purchase={null} suppliers={suppliers.filter((s) => s.is_active).map((s) => ({ id: s.id, name: s.name }))}
        branches={branches.map((b) => ({ id: b.id, name: b.name }))} ingredients={ingredients} />
    </div>
  );
}
