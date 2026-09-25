import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { ChevronRight, History } from 'lucide-react';
import { Badge, Card, EmptyState, type Tone } from '@cafe/ui';
import { formatJalaliDateTime, formatMoney } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import { requireFeature } from '@/lib/billing';
import { formatQty, type Ingredient, type StockMovement } from '@/lib/inventory-types';
import type { Branch } from '@/lib/types';

export const metadata: Metadata = { title: 'گردش ماده‌ی اولیه' };

const TONE: Record<StockMovement['type'], Tone> = { purchase: 'success', sale: 'neutral', sale_reversal: 'info', adjustment: 'info', waste: 'danger', count: 'warning' };

/** One ingredient's ledger: every purchase, sale, reversal, waste, adjustment and count, newest first. */
export default async function IngredientHistoryPage({ params }: PageProps<'/dashboard/inventory/[id]'>) {
  const { can } = await requireMembership();
  if (!can('inventory.view')) redirect('/dashboard');
  await requireFeature('inventory');
  const { id } = await params;

  const [{ data: ingredients }, { data: movements }, { data: branches }] = await Promise.all([
    api<{ data: Ingredient[] }>('/inventory/ingredients'),
    api<{ data: StockMovement[] }>(`/inventory/movements?ingredient_id=${encodeURIComponent(id)}`),
    api<{ data: Branch[] }>('/branches'),
  ]);
  const ingredient = ingredients.find((i) => i.id === id);
  if (!ingredient) notFound();
  const branchName = Object.fromEntries(branches.map((b) => [b.id, b.name]));

  return (
    <div className="flex flex-col gap-6">
      <Link href="/dashboard/inventory" className="inline-flex items-center gap-1 text-sm text-text-muted hover:text-text"><ChevronRight className="size-4" aria-hidden="true" />انبار</Link>
      <PageHeader title={ingredient.name}
        description={`موجودی کل ${formatQty(ingredient.total_quantity, ingredient.unit)} • میانگین خرید ${formatMoney(ingredient.cost_per_big_unit)} برای هر ${ingredient.big_unit_label}`} />

      <Card>
        {movements.length === 0 ? <EmptyState icon={<History />} title="هنوز گردشی ثبت نشده" description="با اولین خرید، فروش یا انبارگردانی اینجا پر می‌شود." /> : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <caption className="sr-only">گردش {ingredient.name}</caption>
              <thead className="border-b border-border text-xs text-text-muted">
                <tr>
                  <th scope="col" className="px-4 py-3 text-start font-medium">زمان</th>
                  <th scope="col" className="px-4 py-3 text-start font-medium">نوع</th>
                  <th scope="col" className="px-4 py-3 text-start font-medium">مقدار</th>
                  <th scope="col" className="px-4 py-3 text-start font-medium">مانده</th>
                  {branches.length > 1 ? <th scope="col" className="px-4 py-3 text-start font-medium">شعبه</th> : null}
                  <th scope="col" className="px-4 py-3 text-start font-medium">شرح</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {movements.map((m) => (
                  <tr key={m.id}>
                    <td className="whitespace-nowrap px-4 py-3 text-text-muted">{formatJalaliDateTime(m.created_at)}</td>
                    <td className="px-4 py-3"><Badge tone={TONE[m.type]} dot>{m.type_label}</Badge></td>
                    <td className={`tabular whitespace-nowrap px-4 py-3 font-semibold ${m.quantity < 0 ? 'text-danger' : 'text-success'}`} dir="ltr" style={{ textAlign: 'end' }}>
                      {m.quantity > 0 ? '+' : ''}{formatQty(m.quantity, ingredient.unit)}
                    </td>
                    <td className="tabular whitespace-nowrap px-4 py-3">{formatQty(m.balance_after, ingredient.unit)}</td>
                    {branches.length > 1 ? <td className="px-4 py-3 text-text-muted">{branchName[m.branch_id]}</td> : null}
                    <td className="px-4 py-3 text-text-muted">
                      {m.order_id ? <Link href={`/dashboard/orders/${m.order_id}`} className="text-brand hover:underline">سفارش</Link> : null}
                      {m.purchase_order_id ? <Link href={`/dashboard/purchases/${m.purchase_order_id}`} className="text-brand hover:underline">{m.note ?? 'خرید'}</Link> : m.note}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  );
}
