import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { AlertTriangle, ClipboardCheck, Coins, Package, ShoppingCart } from 'lucide-react';
import { StatTile } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { requireFeature } from '@/lib/billing';
import type { Ingredient } from '@/lib/inventory-types';
import type { Branch } from '@/lib/types';
import { InventoryManager } from './InventoryManager';

export const metadata: Metadata = { title: 'انبار' };

/** Raw materials: stock per branch, value, low/negative flags, and the everyday actions. */
export default async function InventoryPage({ searchParams }: PageProps<'/dashboard/inventory'>) {
  const { can } = await requireMembership();
  if (!can('inventory.view')) redirect('/dashboard');
  await requireFeature('inventory');
  const params = await searchParams;

  const [{ data: ingredients }, { data: branches }] = await Promise.all([
    api<{ data: Ingredient[] }>('/inventory/ingredients'),
    api<{ data: Branch[] }>('/branches'),
  ]);

  const value = ingredients.reduce((s, i) => s + i.stock_value, 0);
  const low = ingredients.filter((i) => i.is_low && i.is_active).length;
  const negative = ingredients.filter((i) => i.is_negative).length;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="انبار" description="موجودی مواد اولیه در هر شعبه؛ با هر فروش طبق دستور پخت کم و با هر خرید اضافه می‌شود."
        actions={<>
          {can('inventory.manage') ? (
            <Link href="/dashboard/inventory/count" className="inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-surface px-3.5 text-sm font-medium hover:bg-surface-muted">
              <ClipboardCheck className="size-4" aria-hidden="true" />انبارگردانی
            </Link>
          ) : null}
          {can('purchasing.manage') ? (
            <Link href="/dashboard/purchases/new" className="inline-flex h-10 items-center gap-2 rounded-lg bg-brand px-3.5 text-sm font-semibold text-on-brand hover:bg-brand-strong">
              <ShoppingCart className="size-4" aria-hidden="true" />ثبت خرید
            </Link>
          ) : null}
        </>} />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile label="ارزش موجودی" value={formatMoney(value)} icon={<Coins />} hint="به بهای میانگین خرید" />
        <StatTile label="مواد اولیه" value={formatNumber(ingredients.filter((i) => i.is_active).length)} icon={<Package />} />
        <StatTile label="رو به اتمام" value={formatNumber(low)} icon={<AlertTriangle />} hint={low ? 'زیر حد هشدار' : 'همه بالای حد هشدار'} />
        <StatTile label="موجودی منفی" value={formatNumber(negative)} icon={<AlertTriangle />} hint={negative ? 'فروش بیش از موجودی ثبت‌شده؛ انبارگردانی کنید' : 'موردی نیست'} />
      </div>

      <InventoryManager ingredients={ingredients} branches={branches.map((b) => ({ id: b.id, name: b.name }))}
        canManage={can('inventory.manage')} lowOnly={params.low === '1'} />
    </div>
  );
}
