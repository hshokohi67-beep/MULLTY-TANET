import type { Metadata } from 'next';
import Link from 'next/link';
import { Badge, Card, EmptyState, SelectField } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { PageHeader } from '@/components/PageHeader';
import { api } from '@/lib/api';
import { requireMembership } from '@/lib/auth';
import type { Branch, Category, Product } from '@/lib/types';
import { AvailabilityToggle } from './AvailabilityToggle';
import { QuickAddForm } from './QuickAddForm';

export const metadata: Metadata = { title: 'منو' };

export default async function MenuPage({ searchParams }: PageProps<'/dashboard/menu'>) {
  const { can } = await requireMembership();
  const params = await searchParams;
  const search = typeof params.search === 'string' ? params.search : '';
  const categoryId = typeof params.category === 'string' ? params.category : '';

  const query = new URLSearchParams();
  if (search) query.set('search', search);
  if (categoryId) query.set('category_id', categoryId);

  const [{ data: products, meta }, { data: categories }, { data: branches }] = await Promise.all([
    api<{ data: Product[]; meta: { total: number } }>(`/catalog/products?${query}`),
    api<{ data: Category[] }>('/catalog/categories'),
    api<{ data: Branch[] }>('/branches'),
  ]);

  const branchId = typeof params.branch === 'string' && branches.some((b) => b.id === params.branch) ? params.branch : branches[0]?.id;
  const canManage = can('catalog.manage') && can('prices.manage');
  const canAvailability = can('availability.manage');
  const filtered = Boolean(search || categoryId);

  return (
    <div className="flex flex-col gap-5">
      <PageHeader title="آیتم‌های منو" description={`${formatNumber(meta.total)} آیتم`} />

      {canManage ? <QuickAddForm categories={categories} /> : null}

      <form className="flex flex-wrap items-end gap-3" role="search" aria-label="جستجو در منو">
        <div className="min-w-48 flex-1">
          <label htmlFor="menu-search" className="mb-1.5 block text-sm font-medium">جستجو</label>
          <input
            id="menu-search"
            name="search"
            defaultValue={search}
            placeholder="نام یا توضیحات آیتم…"
            className="h-10 w-full rounded-md border border-border-strong bg-surface px-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none"
          />
        </div>
        <div className="w-48">
          <SelectField label="دسته‌بندی" name="category" defaultValue={categoryId}>
            <option value="">همه</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </SelectField>
        </div>
        {branches.length > 1 ? (
          <div className="w-44">
            <SelectField label="شعبه" name="branch" defaultValue={branchId}>
              {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
            </SelectField>
          </div>
        ) : null}
        <button type="submit" className="h-10 rounded-md border border-border-strong bg-surface px-4 text-sm hover:bg-surface-muted">اعمال</button>
      </form>

      <Card>
        {products.length === 0 ? (
          <EmptyState
            title={filtered ? 'آیتمی با این مشخصات پیدا نشد' : 'منو هنوز خالی است'}
            description={filtered ? 'عبارت جستجو یا دسته‌بندی را تغییر دهید.' : 'با «افزودن سریع» بالا، اولین آیتم را فقط با نام و قیمت اضافه کنید.'}
          />
        ) : (
          <ul className="divide-y divide-border">
            {products.map((product) => {
              const availability = product.availability?.find((a) => a.branch_id === branchId);
              const soldOut = availability?.status === 'sold_out';
              const cover = product.images?.[0];

              return (
                <li key={product.id} className="flex items-center gap-3 px-4 py-3">
                  {cover ? (
                    // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
                    <img src={cover.url} alt="" className="size-12 shrink-0 rounded-md object-cover" />
                  ) : (
                    <div className="size-12 shrink-0 rounded-md bg-surface-muted" aria-hidden="true" />
                  )}
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <Link href={`/dashboard/menu/${product.id}`} className="font-medium hover:underline">{product.name}</Link>
                      {!product.is_active ? <Badge>غیرفعال</Badge> : null}
                      {product.is_featured ? <Badge tone="brand">ویژه</Badge> : null}
                      {soldOut ? <Badge tone="warning">تمام شد</Badge> : null}
                      {availability?.status === 'hidden' ? <Badge>پنهان</Badge> : null}
                    </div>
                    <p className="truncate text-xs text-text-muted">
                      {product.categories?.map((c) => c.name).join('، ') || 'بدون دسته'}
                      {(product.variants?.length ?? 0) > 1 ? ` • ${formatNumber(product.variants!.length)} سایز` : ''}
                    </p>
                  </div>
                  <p className="shrink-0 text-sm font-medium">
                    {product.price_from != null ? ((product.variants?.length ?? 0) > 1 ? `از ${formatMoney(product.price_from)}` : formatMoney(product.price_from)) : '—'}
                  </p>
                  {canAvailability && branchId && availability?.status !== 'hidden' ? (
                    <AvailabilityToggle productId={product.id} branchId={branchId} soldOut={soldOut} name={product.name} />
                  ) : null}
                </li>
              );
            })}
          </ul>
        )}
      </Card>
    </div>
  );
}
