import type { Metadata } from 'next';
import { LayoutGrid } from 'lucide-react';
import { Card, EmptyState } from '@cafe/ui';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Product, Tenant } from '@/lib/types';
import { CollectionEditor, type MenuCollection } from './CollectionEditor';

export const metadata: Metadata = { title: 'منوی مناسبتی' };

/**
 * «منوی مناسبتی»: a few products highlighted above the menu for a while («طعم‌های پاییزی»,
 * «ویژه‌ی یلدا»), with an optional seasonal decoration. Opened prefilled from the occasions calendar.
 */
export default async function CollectionsPage({ searchParams }: PageProps<'/dashboard/menu/collections'>) {
  const { can } = await requireMembership();
  const canManage = can('catalog.manage');
  const [{ data: collections }, { data: products }, { data: tenant }] = await Promise.all([
    api<{ data: MenuCollection[] }>('/catalog/collections'),
    api<{ data: Product[] }>('/catalog/products?per_page=200'),
    api<{ data: Tenant }>('/tenant'),
  ]);
  const q = await searchParams;
  const str = (v: unknown) => (typeof v === 'string' ? v : null);
  const isDay = (v: string | null) => v !== null && /^\d{4}-\d{2}-\d{2}$/.test(v);
  const prefill = q.new === '1' ? {
    title: (str(q.title) ?? '').slice(0, 80),
    theme: str(q.theme),
    occasion_key: str(q.occasion),
    from: isDay(str(q.from)) ? str(q.from) : null,
    to: isDay(str(q.to)) ? str(q.to) : null,
  } : null;
  const options = products.filter((p) => p.is_active).map((p) => ({ id: p.id, name: p.name }));

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-text-muted">
        چند محصول را برای یک فصل یا مناسبت بالای منو برجسته کنید؛ سر تاریخش خودبه‌خود تمام می‌شود. تزئین فصلی (برگ پاییز، انار یلدا، برف…) هم با آن روی صفحه‌های فروشگاه می‌آید.
      </p>
      {canManage ? <CollectionEditor products={options} timezone={tenant.timezone} prefill={prefill} /> : null}
      {collections.length === 0 ? (
        <Card><EmptyState icon={<LayoutGrid />} title="هنوز منوی مناسبتی ندارید" description="مثلاً «طعم‌های پاییزی» با نوشیدنی‌های دارچینی، یا «ویژه‌ی یلدا» با نوشیدنی انار." /></Card>
      ) : collections.map((c) => (
        <CollectionEditor key={c.id} collection={c} products={options} timezone={tenant.timezone} readOnly={!canManage} />
      ))}
    </div>
  );
}
